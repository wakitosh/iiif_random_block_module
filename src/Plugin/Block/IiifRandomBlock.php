<?php

namespace Drupal\iiif_random_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Utility\Html;

/**
 * Provides an 'IIIF Random Image' Block.
 *
 * @Block(
 * id = "iiif_random_block",
 * admin_label = @Translation("IIIF Random Image Block"),
 * category = @Translation("Custom"),
 * )
 */
class IiifRandomBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Constructs a new IiifRandomBlock instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('config.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $items = [];
    try {
      $query = $this->database->select('iiif_display_images', 'd')
        ->fields('d', ['image_url', 'manifest_url', 'related_url', 'label']);
      $items = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
    catch (\Exception $e) {
      // Using core logger via factory keeps DI pattern consistent.
      $this->loggerFactory->get('iiif_random_block')->error('Could not fetch display images from database: @message', ['@message' => $e->getMessage()]);
    }

    $config = $this->configFactory->get('iiif_random_block.settings');
    $title_max_length = max(0, (int) ($config->get('title_max_length') ?? 0));
    $items = array_map(function (array $item) use ($title_max_length) {
      $label = (string) ($item['label'] ?? '');
      $item['full_label'] = $label;
      if ($title_max_length > 0 && mb_strlen($label) > $title_max_length) {
        $item['label'] = mb_substr($label, 0, $title_max_length) . '...';
      }
      return $item;
    }, $items);

    // Compute aspect ratio string like "1 / 1" for CSS.
    $compute_ratio = function ($mode, $w, $h, $fallback) {
      $ratio = $fallback;
      if ($mode === '4_3') {
        $ratio = '4 / 3';
      }
      elseif ($mode === '16_9') {
        $ratio = '16 / 9';
      }
      elseif ($mode === '1_1') {
        $ratio = '1 / 1';
      }
      elseif ($mode === 'custom') {
        $w = max(1, (int) $w);
        $h = max(1, (int) $h);
        $ratio = $w . ' / ' . $h;
      }
      return $ratio;
    };

    $mode = $config->get('aspect_ratio_mode') ?: '1_1';
    $ratio = $compute_ratio($mode, $config->get('aspect_ratio_custom_width'), $config->get('aspect_ratio_custom_height'), '1 / 1');
    $mode_md = $config->get('aspect_ratio_mode_md') ?: '';
    $mode_sm = $config->get('aspect_ratio_mode_sm') ?: '';
    $ratio_md = $mode_md !== '' ? $compute_ratio($mode_md, $config->get('aspect_ratio_custom_width_md'), $config->get('aspect_ratio_custom_height_md'), $ratio) : $ratio;
    $ratio_sm = $mode_sm !== '' ? $compute_ratio($mode_sm, $config->get('aspect_ratio_custom_width_sm'), $config->get('aspect_ratio_custom_height_sm'), $ratio_md) : $ratio_md;
    $bp_sm = (int) ($config->get('breakpoint_sm_max') ?? 599);
    $bp_md = (int) ($config->get('breakpoint_md_max') ?? 1023);
    $wrapper_id = Html::getUniqueId('iiif-carousel');

    $build['content'] = [
      '#theme' => 'iiif_random_block',

      '#items' => $items,
      '#wrapper_id' => $wrapper_id,
      '#source_link_text' => $config->get('source_link_text'),
      '#source_link' => $config->get('source_link_url'),
      '#aspect_ratio' => $ratio,
      '#aspect_ratio_md' => $ratio_md,
      '#aspect_ratio_sm' => $ratio_sm,
      '#breakpoint_sm_max' => $bp_sm,
      '#breakpoint_md_max' => $bp_md,
      '#info_button_enabled' => $config->get('info_button_enabled') !== NULL ? (bool) $config->get('info_button_enabled') : TRUE,
      // Provide processed text for info panel.
      '#info_text' => [
        '#type' => 'processed_text',
        '#text' => (string) ($config->get('info_text.value') ?: ''),
        '#format' => (string) ($config->get('info_text.format') ?: filter_default_format()),
      ],
    ];

    // Attach the carousel library and settings.
    $duration = $config->get('carousel_duration') ?: 10;
    $build['#attached']['library'][] = 'iiif_random_block/carousel';
    $build['#attached']['drupalSettings']['iiif_random_block']['carousel']['duration'] = $duration * 1000;
    $build['#attached']['drupalSettings']['iiif_random_block']['carousel']['infoEnabled'] = $build['content']['#info_button_enabled'];

    // Provide correct cacheability so changes in settings are reflected
    // immediately without requiring a full cache rebuild. When the
    // configuration is saved, its cache tag will be invalidated and this
    // block will be recomputed.
    $build['#cache']['tags'] = $config->getCacheTags();
    $build['#cache']['contexts'][] = 'languages:language_interface';

    return $build;
  }

}
