<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Service;

use Magento\Catalog\Model\Product\Image\ParamsBuilder as ProductImageParamsBuilder;
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
        $this->addImageIndexToCache($imageIndex);

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

    private function addImageIndexToCache(string $imageIndex): void
    {
        $this->loadCachedImageIndexes();

        if (!array_key_exists($imageIndex, $this->cachedImageIndexes) || $this->cachedImageIndexes[$imageIndex] < $this->anHourAgoTimestamp) {
            $this->cachedImageIndexes[$imageIndex] = time();

            $this->cache->save($this->jsonSerializer->serialize($this->cachedImageIndexes), self::CACHE_IDENTIFIER, [], self::CACHE_LIFETIME);

            $this->logger->critical('*** save ***');
            $this->logger->critical(print_r($this->cachedImageIndexes, true));

            // TODO: save the new/updated ImageIndex to database table with its timestamp of last used
            // using a random number, every now and again, also cleanup all records from that database table that are older than 24 hours for example (as sort of garbage collector)
        }
    }
}
