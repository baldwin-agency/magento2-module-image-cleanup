<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Deleter;

use Baldwin\ImageCleanup\DataObject\GalleryValue;
use Baldwin\ImageCleanup\Logger\Logger;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as GalleryResourceModel;
use Magento\Framework\App\ResourceConnection;

class DatabaseGalleryDeleter
{
    private $resource;
    private $logger;

    /** @var array<string> */
    private $deletedValueIds = [];

    /** @var int */
    private $deletedValuesCount = 0;

    public function __construct(
        ResourceConnection $resource,
        Logger $logger
    ) {
        $this->resource = $resource;
        $this->logger = $logger;
    }

    /**
     * @param array<GalleryValue> $values
     */
    public function deleteGalleryValues(array $values): void
    {
        $this->resetState();

        $valueIds = array_map(
            function (GalleryValue $value) {
                return $value->getValueId();
            },
            $values
        );

        $valueIdChunks = array_chunk($valueIds, 5000);
        foreach ($valueIdChunks as $valueIdChunk) {
            $deleteResult = $this->resource->getConnection()->delete(
                $this->resource->getTableName(GalleryResourceModel::GALLERY_TABLE),
                [
                    $this->resource->getConnection()->quoteInto('value_id IN (?)', $valueIdChunk),
                ]
            );

            $this->deletedValuesCount += $deleteResult;
            foreach ($valueIdChunk as $valueId) {
                $prefixedValueId = sprintf('valueId: %d', $valueId);

                $this->deletedValueIds[] = $prefixedValueId;
                $this->logger->logDbRowRemoval($prefixedValueId, GalleryResourceModel::GALLERY_TABLE);
            }
        }
    }

    private function resetState(): void
    {
        $this->deletedValueIds = [];
        $this->deletedValuesCount = 0;
    }

    /**
     * @return array<string>
     */
    public function getDeletedValues(): array
    {
        return $this->deletedValueIds;
    }

    public function getNumberOfValuesDeleted(): int
    {
        return $this->deletedValuesCount;
    }
}
