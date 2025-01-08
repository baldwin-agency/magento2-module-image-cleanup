<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Service;

use Magento\Catalog\Model\Product\Image\ParamsBuilder as ProductImageParamsBuilder;
use Magento\Catalog\Model\View\Asset\Image as CatalogImageAsset;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

class StoreImageIndexes
{
    private const CACHE_IDENTIFIER = 'baldwin_imagecleanup_image_indexes_used_recently';
    private const CACHE_LIFETIME = 3600;

    private $logger;
    private $cache;
    private $jsonSerializer;

    /** @var null|array<string, int> */
    private $cachedImageIndexes = null;
    private $anHourAgoTimestamp;

    public function __construct(
        LoggerInterface $logger,
        CacheInterface $cache,
        JsonSerializer $jsonSerializer
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
        $this->jsonSerializer = $jsonSerializer;

        $this->anHourAgoTimestamp = strtotime('1 hour ago');
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    public function afterBuild(ProductImageParamsBuilder $subject, array $result): array
    {
        $imageIndex = $this->getUniqueImageIndex($result);
        $this->addImageIndexToCache($imageIndex, $result);

        return $result;
    }

    public function afterGetUrl(CatalogImageAsset $subject, string $result): string
    {
        $matches = [];
        if (preg_match('#/catalog/product/cache/([a-f0-9]{32})/#', $result, $matches) === 1) {
            $imageIndex = $matches[1];
            $this->addImageIndexToCache($imageIndex, $subject->getImageTransformationParameters());
        }

        return $result;
    }

    /**
     * Copied from Magento\MediaStorage\Service\ImageResize - this hasn't been changed between Magento 2.3.0 and 2.4.7
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

    private function loadCachedImageIndexes(): void
    {
        if ($this->cachedImageIndexes === null) {
            $this->cachedImageIndexes = $this->jsonSerializer->unserialize($this->cache->load(self::CACHE_IDENTIFIER) ?: '[]');

            $this->logger->critical((string) $this->anHourAgoTimestamp);

            $this->logger->critical('*** load ***');
            $this->logger->critical(print_r($this->cachedImageIndexes, true));
        }
    }

    /**
     * @param array<string, mixed> $imageParams
     */
    private function addImageIndexToCache(string $imageIndex, array $imageParams): void
    {
        $this->loadCachedImageIndexes();

        if (!array_key_exists($imageIndex, $this->cachedImageIndexes) || $this->cachedImageIndexes[$imageIndex] < $this->anHourAgoTimestamp) {
            $this->cachedImageIndexes[$imageIndex] = time();

            $this->cache->save($this->jsonSerializer->serialize($this->cachedImageIndexes), self::CACHE_IDENTIFIER, [], self::CACHE_LIFETIME);

            $this->logger->critical('*** save ***');
            $this->logger->critical($imageIndex);
            $this->logger->critical(print_r($this->cachedImageIndexes, true));
            $imageParams = json_encode(array_filter($imageParams));
            if ($imageParams !== false) {
                $this->logger->critical($imageParams);
            }

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($backtrace as $item) {
                if (array_key_exists('file', $item) && str_ends_with($item['file'], '.phtml')) {
                    $sourceFile = $item['file'];
                    if (array_key_exists('line', $item)) {
                        $sourceFile .= ':' . $item['line'];
                    }

                    $this->logger->critical($sourceFile);
                    break;
                }
            }

            // TODO: save the new/updated ImageIndex to database table with its timestamp of last used
            // might be useful to put some extra info in database, I'm currently thinking of:
            // - imageIndex
            // - timestamp last seen
            // - imageParams that generated it (can be useful to figure out where a certain imageIndex came from)
            // - source file that used the imageIndex (can be useful to figure out where a certain imageIndex came from) - can other files besides phtml pull one in? Maybe html files? Any others?

            // then, using a random number - as not do it every single time - also cleanup all records from that database table that are older than 24 hours for example (as sort of garbage collector)
        }
    }
}
