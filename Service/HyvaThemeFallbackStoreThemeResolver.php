<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Service;

use Hyva\ThemeFallback\Config\ThemeFallback as HyvaThemeFallbackConfig;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\View\Design\Theme\ThemeProviderInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation as AppEmulation;
use Magento\Theme\Model\Theme\StoreThemesResolverInterface;

class HyvaThemeFallbackStoreThemeResolver implements StoreThemesResolverInterface
{
    private $appEmulation;
    private $themeProvider;

    /** @var ?HyvaThemeFallbackConfig */
    private $hyvaThemeFallbackConfig = null;

    public function __construct(
        AppEmulation $appEmulation,
        ThemeProviderInterface $themeProvider
    ) {
        $this->themeProvider = $themeProvider;
        $this->appEmulation = $appEmulation;
    }

    public function getThemes(StoreInterface $store): array
    {
        $themeIds = [];

        if (class_exists(HyvaThemeFallbackConfig::class)) {
            $hyvaFallbackThemeId = $this->getHyvaFallbackThemeId($store);
            if ($hyvaFallbackThemeId !== null) {
                $themeIds[] = $hyvaFallbackThemeId;
            }
        }

        return $themeIds;
    }

    private function getHyvaFallbackThemeId(StoreInterface $store): ?int
    {
        $themeId = null;
        $hyvaThemeFallbackConfig = $this->getHyvaThemeFallbackConfig();

        // need to emulate frontend storeview
        // because we can't pass on the incoming store param to the calls to hyvaThemeFallbackConfig unfortunately
        $this->appEmulation->startEnvironmentEmulation($store->getId());

        if ($hyvaThemeFallbackConfig->isEnabled()) {
            $fallbackThemePath = $hyvaThemeFallbackConfig->getThemeFullPath();
            $fallbackTheme = $this->themeProvider->getThemeByFullPath($fallbackThemePath);

            $fallbackThemeId = $fallbackTheme->getId();

            if ($fallbackThemeId !== null && is_numeric($fallbackThemeId)) {
                $themeId = (int) $fallbackThemeId;
            }
        }

        $this->appEmulation->stopEnvironmentEmulation();

        return $themeId;
    }

    /**
     * Can't inject in constructor because it's a soft dependency
     */
    private function getHyvaThemeFallbackConfig(): HyvaThemeFallbackConfig
    {
        if ($this->hyvaThemeFallbackConfig === null) {
            /** @var HyvaThemeFallbackConfig $hyvaThemeFallbackConfig */
            $hyvaThemeFallbackConfig = ObjectManager::getInstance()->get(HyvaThemeFallbackConfig::class);

            $this->hyvaThemeFallbackConfig = $hyvaThemeFallbackConfig;
        }

        return $this->hyvaThemeFallbackConfig;
    }
}
