<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Deleter;

use Baldwin\ImageCleanup\DataObject\GalleryValue;
use Baldwin\ImageCleanup\Logger\Logger;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as GalleryResourceModel;

class DatabaseGalleryDeleter
{
    private $logger;

    /** @var array<GalleryValue> */
    private $deletedValues = [];

    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @param array<GalleryValue> $values
     */
    public function deleteGalleryValues(array $values): void
    {
        $this->resetState();

        // TODO !

        foreach ($values as $value) {
            $this->logger->logDbRowRemoval((string) $value, GalleryResourceModel::GALLERY_TABLE);
        }

        $this->deletedValues = $values;
    }

    private function resetState(): void
    {
        $this->deletedValues = [];
    }

    /**
     * @return array<GalleryValue>
     */
    public function getDeletedValues(): array
    {
        return $this->deletedValues;
    }

    public function getNumberOfValuesDeleted(): int
    {
        return count($this->deletedValues);
    }
}
