# Hryvinskyi_DataLayer

Core of the GA4 data layer: settings, the event catalogue and channels, event and item builders, list and promotion data, the session queue, Google Ads conversion and the settings migration. Renders nothing itself.

Part of the GA4 Data Layer for Magento 2. The full user guide, covering setup, every setting and how to check what the store sends, is in [`docs/ga4-data-layer-guide.md`](https://github.com/hryvinskyi/magento2-data-layer/blob/main/docs/ga4-data-layer-guide.md). Shop staff who want step-by-step tasks instead can use the [user manual](https://github.com/hryvinskyi/magento2-data-layer/blob/main/docs/ga4-data-layer-user-manual.md). Both are also in `docs/` as PDF, DOCX and DOC.

## Installation

Install all GA4 Data Layer modules with the metapackage:

```bash
composer require hryvinskyi/magento2-data-layer-pack
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

or this module alone:

```bash
composer require hryvinskyi/magento2-data-layer
```

## Requirements

- PHP ~8.3.0 or ~8.4.0
- `hryvinskyi/magento2-data-layer-api`
- Magento 2.4.x
