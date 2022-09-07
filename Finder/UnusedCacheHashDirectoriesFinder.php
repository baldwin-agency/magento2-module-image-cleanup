<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Finder;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\View\Asset\ImageFactory as AssetImageFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface as ReadDirectory;
use Magento\Framework\Filesystem\Driver\File as FilesystemFileDriver;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\Config\Customization as ThemeCustomizationConfig;
use Magento\Theme\Model\ResourceModel\Theme\Collection as ThemeCollection;
use Magento\Theme\Model\Theme;

class UnusedCacheHashDirectoriesFinder
{
    private $paramsBuilder;
    private $assetImageFactory;
    private $filesystem;
    private $filesystemFileDriver;
    private $viewConfig;
    private $storeManager;
    private $themeCustomizationConfig;
    private $themeCollection;

    public function __construct(
        ParamsBuilder $paramsBuilder,
        AssetImageFactory $assetImageFactory,
        Filesystem $filesystem,
        FilesystemFileDriver $filesystemFileDriver,
        ViewConfig $viewConfig,
        StoreManagerInterface $storeManager,
        ThemeCustomizationConfig $themeCustomizationConfig,
        ThemeCollection $themeCollection
    ) {
        $this->paramsBuilder = $paramsBuilder;
        $this->assetImageFactory = $assetImageFactory;
        $this->filesystem = $filesystem;
        $this->filesystemFileDriver = $filesystemFileDriver;
        $this->viewConfig = $viewConfig;
        $this->storeManager = $storeManager;
        $this->themeCustomizationConfig = $themeCustomizationConfig;
        $this->themeCollection = $themeCollection;
    }

    /**
     * @return array<string>
     */
    public function find(): array
    {
        $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

        $allDirectories = $mediaDirectory->read('catalog/product/cache');
        $usedDirectories = $this->getUsedCacheHashDirectories($mediaDirectory);

        /** @var array<string> */
        $unusedDirectories = array_diff($allDirectories, $usedDirectories);

        $unusedDirectories = array_map(function (string $directory) use ($mediaDirectory): string {
            $absolutePath = $this->filesystemFileDriver->getRealPath($mediaDirectory->getAbsolutePath($directory));

            if (!is_string($absolutePath)) {
                throw new FileSystemException(
                    __('Can\'t find media directory path: "%1"', $mediaDirectory->getAbsolutePath($directory))
                );
            }

            return $absolutePath;
        }, $unusedDirectories);

        return $unusedDirectories;
    }

    /**
     * @return array<string>
     */
    private function getUsedCacheHashDirectories(ReadDirectory $mediaDirectory): array
    {
        $directories = [];

        $originalImageName = 'baldwin_imagecleanup_test_file.jpg';

        foreach ($this->getViewImages($this->getThemesInUse()) as $imageParams) {
            // Copied first few lines from Magento\MediaStorage\Service\ImageResize::resize
            // - this hasn't been changed between Magento 2.3.4 and 2.4.5
            // - there were some small changes introduced in 2.4.0, but those won't affect the result over here
            unset($imageParams['id']);
            $imageAsset = $this->assetImageFactory->create(
                [
                    'miscParams' => $imageParams,
                    'filePath'   => $originalImageName,
                ]
            );
            $imageAssetPath = $imageAsset->getPath();
            $mediaStorageFilename = $mediaDirectory->getRelativePath($imageAssetPath);
            // End Copied code

            if (preg_match(
                '#^(catalog/product/cache/[a-f0-9]{32})/' . preg_quote($originalImageName) . '$#',
                $mediaStorageFilename,
                $matches
            ) !== 1) {
                throw new LocalizedException(
                    __('File: "%1" doesn\'t seem like a valid cache directory', $mediaStorageFilename)
                );
            }
            $directory = $matches[1];

            $directories[] = $directory;
        }

        $directories = array_unique($directories);

        return $directories;
    }

    /**
     * Copied from Magento\MediaStorage\Service\ImageResize - this hasn't been changed between Magento 2.3.4 and 2.4.5
     *
     * @param array<Theme> $themes
     *
     * @return array<string, array<string, mixed>>
     */
    private function getViewImages(array $themes): array
    {
        $viewImages = [];
        $stores = $this->storeManager->getStores(true);
        /** @var Theme $theme */
        foreach ($themes as $theme) {
            $config = $this->viewConfig->getViewConfig(
                [
                    'area'       => Area::AREA_FRONTEND,
                    'themeModel' => $theme,
                ]
            );
            $images = $config->getMediaEntities('Magento_Catalog', ImageHelper::MEDIA_TYPE_CONFIG_NODE);
            foreach ($images as $imageId => $imageData) {
                foreach ($stores as $store) {
                    $data = $this->paramsBuilder->build($imageData, (int) $store->getId());
                    $uniqIndex = $this->getUniqueImageIndex($data);
                    $data['id'] = $imageId;
                    $viewImages[$uniqIndex] = $data;
                }
            }
        }

        return $viewImages;
    }

    /**
     * Copied from Magento\MediaStorage\Service\ImageResize - this hasn't been changed between Magento 2.3.0 and 2.4.5
     *
     * @param array<string, mixed> $imageData
     */
    private function getUniqueImageIndex(array $imageData): string
    {
        ksort($imageData);
        unset($imageData['type']);

        // phpcs:ignore Magento2.Security.InsecureFunction
        return md5(json_encode($imageData) ?: '');
    }

    /**
     * Copied from Magento\MediaStorage\Service\ImageResize - this hasn't been changed between Magento 2.3.0 and 2.4.5
     *
     * @return array<Theme>
     */
    private function getThemesInUse(): array
    {
        $themesInUse = [];
        /** @var ThemeCollection<Theme> */
        $registeredThemes = $this->themeCollection->loadRegisteredThemes();
        $storesByThemes = $this->themeCustomizationConfig->getStoresByThemes();
        $keyType = is_int(key($storesByThemes)) ? 'getId' : 'getCode';
        foreach ($registeredThemes as $registeredTheme) {
            if (array_key_exists($registeredTheme->$keyType(), $storesByThemes)) {
                $themesInUse[] = $registeredTheme;
            }
        }

        return $themesInUse;
    }
}
