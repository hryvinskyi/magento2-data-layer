# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.4] - 2026-10-22

### Added

- `Api\Catalog\AttributeTextReaderInterface`: turns an attribute's stored values into the text a shopper reads. The reader is chosen by the attribute's input type from the `readers` argument of `Model\Catalog\AttributeTextReader`; dropdown and multiselect give their option labels, any other input its stored value.

### Fixed

- `item_brand` is sent from a text brand attribute: the text it holds is the brand. Only option attributes gave a brand before.

### Changed

- User guide and User Manual: the brand attribute may be a text, dropdown or multiselect attribute. PDF, DOCX and DOC are now `docs/Hryvinskyi_DataLayer-user-guide-1.0.4.*` and `docs/Hryvinskyi_DataLayer-user-manual-1.0.4.*`.

## [1.0.3] - 2026-10-20

### Added

- User Manual for shop staff: step-by-step tasks from first setup to troubleshooting (`docs/ga4-data-layer-user-manual.md`, with PDF, DOCX and DOC as `docs/Hryvinskyi_DataLayer-user-manual-1.0.3.*`).

### Changed

- User guide: the cache type is named Page Cache, as in Cache Management; the step 5 screenshot caption matches the screenshot. PDF, DOCX and DOC are now `docs/Hryvinskyi_DataLayer-user-guide-1.0.3.*`.

## [1.0.2] - 2026-10-20

### Added

- The user guide as PDF, DOCX and DOC (`docs/Hryvinskyi_DataLayer-user-guide-1.0.2.*`), generated from `docs/ga4-data-layer-guide.md`.

## [1.0.1] - 2026-10-20

### Changed

- User guide: settings names and screenshots (Sales menu, Server-side group, wizard step 5) follow the 1.0.1 admin.

## [1.0.0] - 2026-10-20

### Added

- First public release of `Hryvinskyi_DataLayer`.
