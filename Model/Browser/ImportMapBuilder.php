<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Browser;

use Hryvinskyi\DataLayer\Api\Browser\ImportMapBuilderInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Asset\Repository;

/**
 * Resolves named module scripts through the asset repository and writes them as an import map
 *
 * The repository resolves a file id for whatever design area, theme and locale the request runs in, so the storefront
 * and the admin share this. An entry whose value is not a module script's file id fails loudly, naming the entry, so a
 * typo is found on the first page that prints the map instead of a module failing to load in the browser.
 */
class ImportMapBuilder implements ImportMapBuilderInterface
{
    private const FILE_ID = '/^[A-Za-z0-9]+_[A-Za-z0-9]+::[A-Za-z0-9_\/.-]+\.m?js$/D';

    /**
     * @param Repository $assetRepository Resolves a file id for the current design area, theme and locale
     * @param SerializerInterface $serializer Encodes JSON safe to print inside a script element
     */
    public function __construct(
        private readonly Repository $assetRepository,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function addresses(array $fileIds): array
    {
        $addresses = [];
        foreach ($fileIds as $name => $fileId) {
            if (!is_string($name) || trim($name) === '' || !is_string($fileId)
                || preg_match(self::FILE_ID, $fileId) !== 1
            ) {
                throw new \InvalidArgumentException(sprintf(
                    'The browser module name "%s" must map to the file id of a module script '
                    . '(Vendor_Module::js/file.js).',
                    (string) $name
                ));
            }
            $addresses[$name] = $this->assetRepository->getUrl($fileId);
        }

        return $addresses;
    }

    /**
     * @inheritDoc
     */
    public function json(array $addresses): string
    {
        $json = $this->serializer->serialize(['imports' => $addresses === [] ? (object) [] : $addresses]);
        if (!is_string($json)) {
            throw new \InvalidArgumentException('The import map of the browser modules could not be encoded.');
        }

        return $json;
    }
}
