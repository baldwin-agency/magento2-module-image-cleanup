<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ConfigReader
{
    public const CONFIG_PATH_ALT_EXTENSIONS = 'catalog/baldwin_imagecleanup_settings/alternative_extensions';

    private $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array<string>
     */
    public function getAlternativeExtensions(): array
    {
        $extensions = $this->scopeConfig->getValue(
            self::CONFIG_PATH_ALT_EXTENSIONS
        );

        if (!is_string($extensions)) {
            return [];
        }

        // array_diff is to remove empty strings from the array
        $extensions = array_diff(explode(',', trim($extensions)), ['']);

        // remove spaces and dots in case somebody did add those
        $extensions = array_map(function (string $ext) {
            return trim(trim($ext), '.');
        }, $extensions);

        return $extensions;
    }
}
