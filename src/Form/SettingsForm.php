<?php

namespace Drupal\iiif_random_block\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure IIIF Random Block settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a SettingsForm object.
   */
  public function __construct(Connection $database, RendererInterface $renderer) {
    $this->database = $database;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iiif_random_block_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['iiif_random_block.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('iiif_random_block.settings');

    // Currently Displayed Images.
    $form['current_images'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Currently Displayed Images'),
    ];
    try {
      // Add image_url to the query.
      $query = $this->database->select('iiif_display_images', 'd')
        ->fields('d', ['label', 'manifest_url', 'related_url', 'image_url']);
      $results = $query->execute()->fetchAll();

      if (empty($results)) {
        $form['current_images']['info'] = [
          '#markup' => $this->t('No images are currently selected. Save this form to generate the initial set.'),
        ];
      }
      else {
        $items = [];
        foreach ($results as $item) {
          // Build the links with slashes as separators.
          $items[] = [
            '#markup' => $this->t('@label (<a href="@image_url" target="_blank">Image</a> / <a href="@manifest_url" target="_blank">Manifest</a> / <a href="@related_url" target="_blank">Source Page</a>)', [
              '@label' => $item->label,
              '@image_url' => $item->image_url,
              '@manifest_url' => $item->manifest_url,
              '@related_url' => $item->related_url,
            ]),
          ];
        }
        $form['current_images']['list'] = [
          '#theme' => 'item_list',
          '#items' => $items,
          '#title' => $this->t('The following images are currently being displayed in the block:'),
        ];
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not load the list of current images.'));
    }

    // Source Settings...
    $form['source_settings'] = ['#type' => 'fieldset', '#title' => $this->t('Source Information')];
    $form['source_settings']['source_link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source Name'),
      '#description' => $this->t('Leave blank to hide the source information. This label is translatable (Configuration translation).'),
      '#default_value' => $config->get('source_link_text'),
    ];
    $form['source_settings']['source_link_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Source URL'),
      '#default_value' => $config->get('source_link_url'),
    ];

    // IIIF v3 item URL pattern.
    $form['source_settings']['v3_item_url_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IIIF v3 item URL pattern'),
      '#description' => $this->t('If a v3 manifest lacks a homepage/related URL, construct the item page URL using this pattern. Use {identifier} as a placeholder. Example: "/s/mysite/document/{identifier}" or a full URL like "https://example.org/s/mysite/document/{identifier}".'),
      '#placeholder' => '/s/{site-slug}/document/{identifier}',
      '#default_value' => (string) ($config->get('v3_item_url_pattern') ?? ''),
    ];

    // Display Settings...
    $form['display_settings'] = ['#type' => 'fieldset', '#title' => $this->t('Display Settings')];
    $form['display_settings']['number_of_images'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of images to display'),
      '#default_value' => $config->get('number_of_images') ?: 5,
    ];
    $form['display_settings']['carousel_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Carousel duration'),
      '#default_value' => $config->get('carousel_duration') ?: 10,
      '#field_suffix' => $this->t('seconds'),
    ];
    $form['display_settings']['image_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Image size (max pixels)'),
      '#default_value' => $config->get('image_size') ?: 800,
      '#field_suffix' => $this->t('px'),
    ];
    $form['display_settings']['title_max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Item title max length'),
      '#default_value' => (int) ($config->get('title_max_length') ?? 0),
      '#min' => 0,
      '#description' => $this->t('Maximum number of characters shown under each image. If a title exceeds this length, it will be truncated and appended with "...". Set to 0 to disable truncation.'),
    ];

    // IIIF cropping (percent-based, applied to all images).
    $form['display_settings']['crop'] = [
      '#type' => 'details',
      '#title' => $this->t('IIIF cropping (percent)'),
      '#open' => FALSE,
      '#description' => $this->t('Trim the IIIF image from each edge, in percent of the full image (0–100). The remaining region is requested via the IIIF Image API using a pct: region.'),
      '#weight' => 70,
    ];
    $form['display_settings']['crop']['crop_top'] = [
      '#type' => 'number',
      '#title' => $this->t('Top'),
      '#min' => 0,
      '#max' => 100,
      '#default_value' => (int) ($config->get('crop_top') ?? 0),
      '#field_suffix' => '%',
    ];
    $form['display_settings']['crop']['crop_right'] = [
      '#type' => 'number',
      '#title' => $this->t('Right'),
      '#min' => 0,
      '#max' => 100,
      '#default_value' => (int) ($config->get('crop_right') ?? 0),
      '#field_suffix' => '%',
    ];
    $form['display_settings']['crop']['crop_bottom'] = [
      '#type' => 'number',
      '#title' => $this->t('Bottom'),
      '#min' => 0,
      '#max' => 100,
      '#default_value' => (int) ($config->get('crop_bottom') ?? 0),
      '#field_suffix' => '%',
    ];
    $form['display_settings']['crop']['crop_left'] = [
      '#type' => 'number',
      '#title' => $this->t('Left'),
      '#min' => 0,
      '#max' => 100,
      '#default_value' => (int) ($config->get('crop_left') ?? 0),
      '#field_suffix' => '%',
    ];

    // Info panel content (rich text).
    $form['display_settings']['info_button_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable info button'),
      '#description' => $this->t('If unchecked, the ⓘ button and info panel will be hidden entirely.'),
      '#default_value' => $config->get('info_button_enabled') !== NULL ? (bool) $config->get('info_button_enabled') : TRUE,
      '#weight' => 90,
    ];
    $form['display_settings']['info_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Info panel text'),
      '#description' => $this->t('Text shown when clicking the ⓘ icon on a slide.'),
      '#format' => $config->get('info_text.format') ?: filter_default_format(),
      '#default_value' => $config->get('info_text.value') ?: '',
      '#weight' => 91,
      '#states' => [
        'visible' => [
          ':input[name="info_button_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Aspect Ratio Settings...
    $form['display_settings']['aspect_ratio_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Aspect ratio'),
      '#options' => [
        '1_1' => $this->t('1:1'),
        '4_3' => $this->t('4:3'),
        '16_9' => $this->t('16:9'),
        'custom' => $this->t('Custom ratio'),
      ],
      '#default_value' => $config->get('aspect_ratio_mode') ?: '1_1',
      '#description' => $this->t('Displayed images are cropped client-side to the selected ratio using CSS.'),
    ];
    $form['display_settings']['aspect_ratio_custom_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom ratio width'),
      '#min' => 1,
      '#default_value' => $config->get('aspect_ratio_custom_width') ?: 1,
      '#states' => [
        'visible' => [
          ':input[name="aspect_ratio_mode"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $form['display_settings']['aspect_ratio_custom_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom ratio height'),
      '#min' => 1,
      '#default_value' => $config->get('aspect_ratio_custom_height') ?: 1,
      '#states' => [
        'visible' => [
          ':input[name="aspect_ratio_mode"]' => ['value' => 'custom'],
        ],
      ],
    ];

    // Responsive Aspect Ratios.
    $form['display_settings']['responsive'] = [
      '#type' => 'details',
      '#title' => $this->t('Responsive aspect ratios'),
      '#open' => FALSE,
      '#description' => $this->t('Configure alternative aspect ratios for medium and small screens. Leave the mode empty to inherit the default PC setting.'),
      '#weight' => 80,
    ];
    $form['display_settings']['responsive']['breakpoint_sm_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Small screen max width'),
      '#description' => $this->t('Applies up to this width (px).'),
      '#field_suffix' => 'px',
      '#default_value' => $config->get('breakpoint_sm_max') ?? 599,
    ];
    $form['display_settings']['responsive']['breakpoint_md_max'] = [
      '#type' => 'number',
      '#title' => $this->t('Medium screen max width'),
      '#description' => $this->t('Applies up to this width (px). Above this width, the default (PC) setting applies.'),
      '#field_suffix' => 'px',
      '#default_value' => $config->get('breakpoint_md_max') ?? 1023,
    ];
    // Medium screens.
    $form['display_settings']['responsive']['aspect_ratio_mode_md'] = [
      '#type' => 'select',
      '#title' => $this->t('Aspect ratio (medium screens)'),
      '#options' => [
        '' => $this->t('- Inherit default -'),
        '1_1' => $this->t('1:1'),
        '4_3' => $this->t('4:3'),
        '16_9' => $this->t('16:9'),
        'custom' => $this->t('Custom ratio'),
      ],
      '#default_value' => $config->get('aspect_ratio_mode_md') ?? '',
      '#weight' => 20,
    ];
    $form['display_settings']['responsive']['aspect_ratio_custom_width_md'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom ratio width (medium)'),
      '#min' => 1,
      '#default_value' => $config->get('aspect_ratio_custom_width_md') ?: 1,
      '#weight' => 21,
      '#states' => [
        'visible' => [
          ':input[name="aspect_ratio_mode_md"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $form['display_settings']['responsive']['aspect_ratio_custom_height_md'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom ratio height (medium)'),
      '#min' => 1,
      '#default_value' => $config->get('aspect_ratio_custom_height_md') ?: 1,
      '#weight' => 22,
      '#states' => [
        'visible' => [
          ':input[name="aspect_ratio_mode_md"]' => ['value' => 'custom'],
        ],
      ],
    ];
    // Small screens.
    $form['display_settings']['responsive']['aspect_ratio_mode_sm'] = [
      '#type' => 'select',
      '#title' => $this->t('Aspect ratio (small screens)'),
      '#options' => [
        '' => $this->t('- Inherit default -'),
        '1_1' => $this->t('1:1'),
        '4_3' => $this->t('4:3'),
        '16_9' => $this->t('16:9'),
        'custom' => $this->t('Custom ratio'),
      ],
      '#default_value' => $config->get('aspect_ratio_mode_sm') ?? '',
      '#weight' => 10,
    ];
    $form['display_settings']['responsive']['aspect_ratio_custom_width_sm'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom ratio width (small)'),
      '#min' => 1,
      '#default_value' => $config->get('aspect_ratio_custom_width_sm') ?: 1,
      '#weight' => 11,
      '#states' => [
        'visible' => [
          ':input[name="aspect_ratio_mode_sm"]' => ['value' => 'custom'],
        ],
      ],
    ];
    $form['display_settings']['responsive']['aspect_ratio_custom_height_sm'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom ratio height (small)'),
      '#min' => 1,
      '#default_value' => $config->get('aspect_ratio_custom_height_sm') ?: 1,
      '#weight' => 12,
      '#states' => [
        'visible' => [
          ':input[name="aspect_ratio_mode_sm"]' => ['value' => 'custom'],
        ],
      ],
    ];

    // Image Selection Rules...
    $default_rules = "1 => 1\n2 => 2\n3+ => random(2-last-1)";
    $form['selection_rules_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Image Selection Rules'),
      '#description' => $this->t('Define rules for selecting an image based on the total number of canvases in a manifest. The first matching rule from the top will be used.'),
    ];
    $form['selection_rules_settings']['selection_rules'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Rules'),
      '#default_value' => $config->get('selection_rules') ?: $default_rules,
      '#rows' => 10,
      '#description' => $this->t('
            <p>Enter one rule per line in the format: <code>Condition => Action</code></p>
            <strong>Conditions:</strong>
            <ul>
                <li><code>5</code>: Exactly 5 canvases.</li>
                <li><code>1-4</code>: Between 1 and 4 canvases (inclusive).</li>
                <li><code>10+</code>: 10 or more canvases.</li>
            </ul>
            <strong>Actions:</strong>
            <ul>
                <li><code>3</code>: Select the 3rd canvas.</li>
                <li><code>last</code>: Select the very last canvas.</li>
                <li><code>random</code>: Select a random canvas from all available.</li>
                <li><code>random(2-last)</code>: Select a random canvas from the 2nd to the last.</li>
                <li><code>random(1-last-1)</code>: Select a random canvas from the 1st to the second to last (excludes the last page).</li>
            </ul>
        '),
    ];

    // Cron Settings.
    $form['cron_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Update Frequency'),
    ];
    $form['cron_settings']['cron_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Update interval'),
      '#description' => $this->t('The interval for updating the list of displayed images. Common values: 1 hour = 3600, 12 hours = 43200, 24 hours = 86400.'),
      '#default_value' => $config->get('cron_interval') ?: 86400,
    ];

    // Manifest URL list...
    $query = $this->database->select('iiif_manifest_urls', 'm')->fields('m', ['url']);
    $results = $query->execute()->fetchCol();
    $urls = implode("\n", $results);
    $form['manifest_urls_fieldset'] = ['#type' => 'fieldset', '#title' => $this->t('Manifest URLs')];
    $form['manifest_urls_fieldset']['manifest_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('IIIF Manifest URLs'),
      '#default_value' => $urls,
      '#rows' => 10,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('iiif_random_block.settings');
    $config
      ->set('source_link_text', $form_state->getValue('source_link_text'))
      ->set('source_link_url', $form_state->getValue('source_link_url'))
      ->set('number_of_images', $form_state->getValue('number_of_images'))
      ->set('carousel_duration', $form_state->getValue('carousel_duration'))
      ->set('image_size', $form_state->getValue('image_size'))
      ->set('title_max_length', (int) $form_state->getValue('title_max_length'))
      ->set('info_button_enabled', (bool) $form_state->getValue('info_button_enabled'))
      ->set('aspect_ratio_mode', $form_state->getValue('aspect_ratio_mode'))
      ->set('aspect_ratio_custom_width', (int) $form_state->getValue('aspect_ratio_custom_width'))
      ->set('aspect_ratio_custom_height', (int) $form_state->getValue('aspect_ratio_custom_height'))
      ->set('breakpoint_sm_max', (int) $form_state->getValue('breakpoint_sm_max'))
      ->set('breakpoint_md_max', (int) $form_state->getValue('breakpoint_md_max'))
      ->set('aspect_ratio_mode_md', (string) $form_state->getValue('aspect_ratio_mode_md'))
      ->set('aspect_ratio_custom_width_md', (int) $form_state->getValue('aspect_ratio_custom_width_md'))
      ->set('aspect_ratio_custom_height_md', (int) $form_state->getValue('aspect_ratio_custom_height_md'))
      ->set('aspect_ratio_mode_sm', (string) $form_state->getValue('aspect_ratio_mode_sm'))
      ->set('aspect_ratio_custom_width_sm', (int) $form_state->getValue('aspect_ratio_custom_width_sm'))
      ->set('aspect_ratio_custom_height_sm', (int) $form_state->getValue('aspect_ratio_custom_height_sm'))
      ->set('crop_top', (int) $form_state->getValue('crop_top'))
      ->set('crop_right', (int) $form_state->getValue('crop_right'))
      ->set('crop_bottom', (int) $form_state->getValue('crop_bottom'))
      ->set('crop_left', (int) $form_state->getValue('crop_left'))
      ->set('selection_rules', $form_state->getValue('selection_rules'))
      ->set('cron_interval', $form_state->getValue('cron_interval'))
      ->set('info_text', [
        'value' => $form_state->getValue('info_text')['value'] ?? '',
        'format' => $form_state->getValue('info_text')['format'] ?? filter_default_format(),
      ])
      ->set('v3_item_url_pattern', (string) $form_state->getValue('v3_item_url_pattern'))
      ->save();

    $urls_text = $form_state->getValue('manifest_urls');
    $urls = preg_split('/\\r\\n|\\r|\\n/', $urls_text, -1, PREG_SPLIT_NO_EMPTY);
    $urls = array_unique(array_filter($urls, fn($url) => filter_var(trim($url), FILTER_VALIDATE_URL)));
    $transaction = $this->database->startTransaction();
    try {
      $this->database->truncate('iiif_manifest_urls')->execute();
      if (!empty($urls)) {
        $query = $this->database->insert('iiif_manifest_urls')->fields(['url']);
        foreach ($urls as $url) {
          $query->values(['url' => trim($url)]);
        }
        $query->execute();
      }
      $this->messenger()->addStatus($this->t('The manifest URL list has been updated.'));
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->messenger()->addError($this->t('An error occurred while updating the URL list.'));
    }

    // Immediately update the displayed images by passing the values directly.
    $update_result = static::updateDisplayedImages(
        (int) $form_state->getValue('number_of_images'),
        (int) $form_state->getValue('image_size'),
        (string) $form_state->getValue('selection_rules')
    );

    if ($update_result) {
      $this->messenger()->addStatus($this->t('The displayed images have been updated immediately with @count items.', ['@count' => $update_result]));
    }
    else {
      $this->messenger()->addWarning($this->t('Could not update the displayed images. Please check the logs.'));
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('aspect_ratio_mode') === 'custom') {
      $w = (int) $form_state->getValue('aspect_ratio_custom_width');
      $h = (int) $form_state->getValue('aspect_ratio_custom_height');
      if ($w < 1) {
        $form_state->setErrorByName('aspect_ratio_custom_width', $this->t('Custom ratio width must be greater than 0.'));
      }
      if ($h < 1) {
        $form_state->setErrorByName('aspect_ratio_custom_height', $this->t('Custom ratio height must be greater than 0.'));
      }
    }
    // Validate responsive custom ratios if specified.
    foreach (['md', 'sm'] as $bp) {
      if ($form_state->getValue('aspect_ratio_mode_' . $bp) === 'custom') {
        $w = (int) $form_state->getValue('aspect_ratio_custom_width_' . $bp);
        $h = (int) $form_state->getValue('aspect_ratio_custom_height_' . $bp);
        if ($w < 1) {
          $form_state->setErrorByName('aspect_ratio_custom_width_' . $bp, $this->t('Custom ratio width must be greater than 0.'));
        }
        if ($h < 1) {
          $form_state->setErrorByName('aspect_ratio_custom_height_' . $bp, $this->t('Custom ratio height must be greater than 0.'));
        }
      }
    }

    // Validate v3 item URL pattern: if provided, must include {identifier}.
    $pattern = (string) $form_state->getValue('v3_item_url_pattern');
    if ($pattern !== '' && strpos($pattern, '{identifier}') === FALSE) {
      $form_state->setErrorByName('v3_item_url_pattern', $this->t('The v3 item URL pattern must include the {identifier} placeholder.'));
    }

    // Validate IIIF cropping: ensure percentages are 0–100 and region is valid.
    $top = max(0, min(100, (int) $form_state->getValue('crop_top')));
    $right = max(0, min(100, (int) $form_state->getValue('crop_right')));
    $bottom = max(0, min(100, (int) $form_state->getValue('crop_bottom')));
    $left = max(0, min(100, (int) $form_state->getValue('crop_left')));
    if ($left + $right >= 100) {
      $form_state->setErrorByName('crop_right', $this->t('The sum of left and right cropping must be less than 100%.'));
    }
    if ($top + $bottom >= 100) {
      $form_state->setErrorByName('crop_bottom', $this->t('The sum of top and bottom cropping must be less than 100%.'));
    }
  }

  /**
   * Selects and updates the images for the block display.
   *
   * @param int $number_of_images
   *   The number of images to select.
   * @param int $image_size
   *   The max size for the image.
   * @param string $selection_rules
   *   The rule string for selecting a canvas.
   *
   * @return int|false
   *   The number of items saved, or FALSE on failure.
   */
  public static function updateDisplayedImages(int $number_of_images, int $image_size, string $selection_rules) {
    $database = \Drupal::database();
    $client_factory = \Drupal::service('http_client_factory');
    $logger = \Drupal::logger('iiif_random_block');

    $config = \Drupal::config('iiif_random_block.settings');
    $crop_top = (int) ($config->get('crop_top') ?? 0);
    $crop_right = (int) ($config->get('crop_right') ?? 0);
    $crop_bottom = (int) ($config->get('crop_bottom') ?? 0);
    $crop_left = (int) ($config->get('crop_left') ?? 0);

    $query = $database->select('iiif_manifest_urls', 'm')->fields('m', ['url'])->orderRandom()->range(0, $number_of_images);
    $manifest_urls = $query->execute()->fetchCol();

    if (count($manifest_urls) < $number_of_images) {
      $logger->warning('Could not retrieve @num random manifests.', ['@num' => $number_of_images]);
      return FALSE;
    }

    $display_data = [];
    $client = $client_factory->fromOptions(['timeout' => 20]);

    foreach ($manifest_urls as $manifest_url) {
      try {
        $response = $client->get($manifest_url);
        $manifest = json_decode((string) $response->getBody(), TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          continue;
        }

        $context = $manifest['@context'] ?? '';
        $is_v3 = is_string($context) && strpos($context, 'presentation/3') !== FALSE;
        $canvases = $is_v3 ? ($manifest['items'] ?? []) : ($manifest['sequences'][0]['canvases'] ?? []);

        $selected_canvas = static::getCanvasByRules($canvases, $selection_rules);
        if (!$selected_canvas) {
          continue;
        }

        $image_service = NULL;
        if ($is_v3) {
          if (isset($selected_canvas['items'][0]['items'][0]['body']['service'])) {
            foreach ($selected_canvas['items'][0]['items'][0]['body']['service'] as $service) {
              if (isset($service['type']) && $service['type'] === 'ImageService3') {
                $image_service = $service['id'] ?? $service['@id'] ?? NULL;
                break;
              }
            }
          }
        }
        else {
          $image_service = $selected_canvas['images'][0]['resource']['service']['@id'] ?? NULL;
        }
        if (!$image_service) {
          continue;
        }

        // Derive related (item) URL with priority:
        // 1) If v3 and a pattern is configured AND identifier can be extracted,
        // build from pattern (preferred over homepage/related).
        // 2) Else, fall back to manifest-provided related/homepage.
        // 3) Else, '#'.
        $related_url = NULL;
        if ($is_v3) {
          $pattern = (string) (\Drupal::config('iiif_random_block.settings')->get('v3_item_url_pattern') ?? '');
          if ($pattern !== '') {
            // Extract dcterms:identifier from v3 metadata.
            $identifier = NULL;
            if (!empty($manifest['metadata']) && is_array($manifest['metadata'])) {
              $identifier = static::extractIdentifierFromV3Metadata($manifest['metadata']);
            }
            if ($identifier) {
              $built = strtr($pattern, [
                '{identifier}' => rawurlencode((string) $identifier),
              ]);
              // If the pattern result is relative, prefix with the manifest's
              // origin (remote server domain), not the Drupal site's domain.
              if (!preg_match('/^https?:\/\//i', $built)) {
                $parts = parse_url($manifest_url);
                $origin = '';
                if (!empty($parts['scheme']) && !empty($parts['host'])) {
                  $origin = $parts['scheme'] . '://' . $parts['host'];
                  if (!empty($parts['port'])) {
                    $origin .= ':' . $parts['port'];
                  }
                }
                if ($origin !== '') {
                  // Ensure exactly one slash between origin and path.
                  if (strpos($built, '/') === 0) {
                    $related_url = rtrim($origin, '/') . $built;
                  }
                  else {
                    $related_url = rtrim($origin, '/') . '/' . ltrim($built, '/');
                  }
                }
                else {
                  // Last resort: keep relative to avoid using Drupal domain.
                  $related_url = $built;
                }
              }
              else {
                $related_url = $built;
              }
            }
          }
        }
        // Fallback to manifest URLs only if pattern not used.
        if (!$related_url) {
          $related_url = $manifest['related']['@id'] ?? ($manifest['homepage'][0]['id'] ?? NULL);
        }
        if (!$related_url) {
          $related_url = '#';
        }

        // ** FIX **: Updated label extraction logic for v3 manifests.
        $label = 'Untitled';
        if (isset($manifest['label'])) {
          if (is_string($manifest['label'])) {
            $label = $manifest['label'];
          }
          elseif (is_array($manifest['label'])) {
            // Handles v3 language maps like {"en": ["Title"], "ja": ["..."]}.
            // It just takes the first available language's first value.
            $first_lang_values = reset($manifest['label']);
            if (is_array($first_lang_values) && isset($first_lang_values[0])) {
              $label = $first_lang_values[0];
            }
            // Fallback for other structures like {"@value": "Title"}.
            elseif (isset($manifest['label']['@value'])) {
              $label = $manifest['label']['@value'];
            }
          }
        }

        // Build IIIF Image API URL with optional pct-based cropping.
        $region = 'full';
        $lt = max(0, min(100, $crop_top));
        $lr = max(0, min(100, $crop_right));
        $lb = max(0, min(100, $crop_bottom));
        $ll = max(0, min(100, $crop_left));
        if ($ll + $lr < 100 && $lt + $lb < 100 && ($ll || $lr || $lt || $lb)) {
          $x = $ll;
          $y = $lt;
          $w = max(1, 100 - $ll - $lr);
          $h = max(1, 100 - $lt - $lb);
          $region = sprintf('pct:%d,%d,%d,%d', $x, $y, $w, $h);
        }

        $display_data[] = [
          'image_url' => rtrim($image_service, '/') . "/$region/$image_size,/0/default.jpg",
          'manifest_url' => $manifest_url,
          'related_url' => $related_url,
          'label' => mb_substr($label, 0, 512),
        ];
      }
      catch (\Exception $e) {
        $logger->error(
          'Failed to process manifest @url: @message',
          [
            '@url' => $manifest_url,
            '@message' => $e->getMessage(),
          ]
        );
      }
    }

    if (!empty($display_data)) {
      $transaction = $database->startTransaction();
      try {
        $database->truncate('iiif_display_images')->execute();
        $query = $database->insert('iiif_display_images')->fields(['image_url', 'manifest_url', 'related_url', 'label']);
        foreach ($display_data as $item) {
          $query->values($item);
        }
        $query->execute();
        return count($display_data);
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        $logger->error('Failed to save display images: @message', ['@message' => $e->getMessage()]);
      }
    }
    return FALSE;
  }

  /**
   * Extracts dcterms:identifier from a IIIF v3 metadata array.
   */
  private static function extractIdentifierFromV3Metadata(array $metadata): ?string {
    foreach ($metadata as $entry) {
      $label = isset($entry['label']) ? static::firstText($entry['label']) : NULL;
      if (!$label) {
        continue;
      }
      $key = strtolower(trim((string) $label));
      if ($key === 'dcterms:identifier' || $key === 'identifier') {
        $val = isset($entry['value']) ? static::firstText($entry['value']) : NULL;
        if (is_string($val) && $val !== '') {
          return $val;
        }
      }
    }
    return NULL;
  }

  /**
   * Returns the first human-readable string from a v2/v3 label/value.
   */
  private static function firstText($value): ?string {
    if (is_string($value)) {
      return $value;
    }
    if (is_array($value)) {
      // Language map: {"en": ["Text"]} or value objects.
      if (isset($value['@value']) && is_string($value['@value'])) {
        return $value['@value'];
      }
      $first = reset($value);
      if (is_array($first)) {
        // Could be ["Text"] or [{"@value": "Text"}].
        $inner = reset($first);
        if (is_string($inner)) {
          return $inner;
        }
        if (is_array($inner) && isset($inner['@value']) && is_string($inner['@value'])) {
          return $inner['@value'];
        }
      }
      elseif (is_string($first)) {
        return $first;
      }
    }
    return NULL;
  }

  /**
   * Helper function to select a canvas based on defined rules.
   */
  private static function getCanvasByRules(array $canvases, string $rules_string): ?array {
    $canvas_count = count($canvases);
    if ($canvas_count === 0) {
      return NULL;
    }
    $rules = preg_split('/\\r\\n|\\r|\\n/', $rules_string, -1, PREG_SPLIT_NO_EMPTY);
    $selected_index = -1;
    $warnings = [];
    // Avoid duplicate warnings for the same rules string within one request.
    static $logged_rule_hashes = [];
    $rules_hash = sha1($rules_string);
    foreach ($rules as $rule) {
      if (strpos($rule, '=>') === FALSE) {
        $warnings[] = 'Invalid rule format (missing "=>"): ' . trim($rule);
        continue;
      }
      [$condition, $action] = array_map('trim', explode('=>', $rule, 2));

      // Validate condition syntax: N, N-M, or N+.
      $condition_valid = (bool) (preg_match('/^\d+$/', $condition)
        || preg_match('/^\d+-\d+$/', $condition)
        || preg_match('/^\d+\+$/', $condition));
      if (!$condition_valid) {
        $warnings[] = 'Invalid condition: ' . $condition;
        continue;
      }
      $condition_met = FALSE;
      if (strpos($condition, '+') !== FALSE) {
        if ($canvas_count >= (int) $condition) {
          $condition_met = TRUE;
        }
      }
      elseif (strpos($condition, '-') !== FALSE) {
        [$min, $max] = array_map('intval', explode('-', $condition));
        if ($canvas_count >= $min && $canvas_count <= $max) {
          $condition_met = TRUE;
        }
      }
      else {
        if ($canvas_count == (int) $condition) {
          $condition_met = TRUE;
        }
      }
      if ($condition_met) {
        $action = strtolower($action);
        if ($action === 'last') {
          // Always select the last index.
          $selected_index = $canvas_count - 1;
          break;
        }
        elseif (strpos($action, 'random') !== FALSE) {
          // Interpret random / random(A-B) / random(A-last[-offset]).
          if (preg_match('/^random\((\d+)-(\d+|last)(-(\d+))?\)$/', $action, $matches)) {
            $rand_min = (int) $matches[1] - 1;
            $end_val = $matches[2];
            $offset = isset($matches[4]) ? (int) $matches[4] : 0;
            $rand_max = ($end_val === 'last') ? $canvas_count - 1 - $offset : (int) $end_val - 1;
            // Clamp range to canvas bounds.
            $rand_min = max(0, $rand_min);
            $rand_max = min($canvas_count - 1, $rand_max);
            if ($rand_min <= $rand_max) {
              $selected_index = mt_rand($rand_min, $rand_max);
              break;
            }
            // Invalid range; continue to next rule.
          }
          elseif ($action === 'random') {
            // Full-range random.
            $selected_index = mt_rand(0, $canvas_count - 1);
            break;
          }
          else {
            // Syntax like "random(...)" but not matching expected pattern.
            $warnings[] = 'Invalid random syntax: ' . $action;
            // Continue to the next rule.
          }
        }
        elseif (preg_match('/^\d+$/', $action)) {
          // Numeric action (1-based).
          $num = (int) $action;
          if ($num <= 0) {
            $warnings[] = 'Invalid numeric action (must be >= 1): ' . $action;
            continue;
          }
          $candidate = $num - 1;
          if ($candidate >= 0 && $candidate < $canvas_count) {
            $selected_index = $candidate;
            break;
          }
          // Out of range; continue to next rule without warning.
        }
        else {
          // Unknown action keyword.
          $warnings[] = 'Unknown action: ' . $action;
          // Continue to the next rule.
        }
      }
    }
    // If no rule produced a valid selection, fall back to global random.
    if ($selected_index < 0) {
      $selected_index = mt_rand(0, $canvas_count - 1);
    }
    // Emit warnings once per request for the same rules string.
    if (!empty($warnings) && empty($logged_rule_hashes[$rules_hash])) {
      // Avoid errors in unit tests without a Drupal container.
      if (class_exists('Drupal\\Drupal') && \Drupal::hasContainer()) {
        \Drupal::logger('iiif_random_block')->warning('Selection rules contain errors; using fallbacks. Problems: @problems', [
          '@problems' => implode(' | ', array_unique($warnings)),
        ]);
      }
      $logged_rule_hashes[$rules_hash] = TRUE;
    }
    if ($selected_index < 0 || $selected_index >= $canvas_count) {
      return NULL;
    }
    return $canvases[$selected_index] ?? NULL;
  }

}
