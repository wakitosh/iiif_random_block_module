# Changelog

All notable changes to IIIF Random Block will be documented in this file.

## [1.4.1] - 2026-06-03

### Added
- Display setting for the maximum length of the item title link shown below each image.

### Changed
- Item titles are now truncated only when they exceed the configured maximum length, with `...` appended.
- A setting value of `0` disables title truncation.

## [1.4.0] - 2025-11-25

### Added
- Carousel dot navigation below the image area, allowing users to explicitly select which slide to view in addition to automatic rotation.
- Percent-based IIIF cropping controls (top/right/bottom/left) in the Display Settings, applied to all images via a `pct:` region in the IIIF Image API URL.

### Changed
- Block image URLs now honor the configured cropping region; when any edge is trimmed and the remaining area is valid, `full` is replaced with a `pct:x,y,w,h` region while preserving the configured max image size.

## [1.3.1] - 2025-10-25

### Changed
- IIIF v3 item URL derivation: When a pattern is configured (IIIF v3 item URL pattern), it is now prioritized over manifest-provided homepage/related.

### Fixed
- Relative patterns are now resolved against the manifest origin (remote server domain), preventing accidental use of the Drupal site domain that led to 404/Not found.

## [1.3.0] - 2025-09-18

### Added
- Responsive aspect ratios: configurable aspect ratios for medium and small screens, with breakpoint max widths. Includes admin settings, schema/defaults, and front-end wiring via CSS variables and media queries.
- Unique wrapper id per block instance to scope responsive CSS and avoid collisions when multiple blocks are on the same page.

### Changed
- Settings UI: Reordered Display Settings so that “Responsive aspect ratios” appears above “Enable info button”.
- Settings UI: Within the responsive section, Small screen fields now appear above Medium screen fields for a top-down mobile-first flow.
- Front-end: Info icon visibility improved — appears on media or item hover, on keyboard focus (focus-within), and shows by default on touch devices.

### Fixed
- Block render array keys and indentation issues (removed accidental quoted keys, normalized indentation).
- Theme hook variables updated to include new responsive fields to prevent undefined Twig variables.

## [1.2.5] - 2025-09-17

### Added
- Setting: Enable/disable the Info button (ⓘ). When disabled, the button, panel markup, and related JS interactions are suppressed.

### Changed
- Admin UI: Moved the toggle just above the Info panel text and hides the text field dynamically when disabled (better clarity).

### Fixed
- Info button visibility toggle now respected in Twig by adding `info_button_enabled` to theme hook variables.


## [1.2.4] - 2025-09-17

### Added
- Info button (ⓘ) appears on hover over the carousel image; clicking opens an information panel the same width as the image.
- New settings field “Info panel text” with rich-text editor; the content is rendered in the info panel (processed text).
- Info panel: Close button (SVG) in the top-right; also closes on outside click and Escape key.
- While the panel is open, the carousel auto-rotation pauses; it resumes when closed.

### Changed
- Info panel styling: minimum height set to half of the image height; left-aligned text; white panel background with subtle border and enhanced shadow for depth.
- Info button visual: updated to provided SVG; white background, black icon; increased size for better usability.

### Fixed
- Immediate reflection of settings changes: Block now uses config cache tags and language context so saving the settings updates the front-end without manual cache clear.

