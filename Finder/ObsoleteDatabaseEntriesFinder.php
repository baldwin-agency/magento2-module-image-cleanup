<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Finder;

use Baldwin\ImageCleanup\DataObject\GalleryValue;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ResourceModel\Product\Gallery as GalleryResourceModel;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\ResourceConnection;

class ObsoleteDatabaseEntriesFinder
{
    private $resource;
    private $attributeRepository;

    public function __construct(
        ResourceConnection $resource,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->resource = $resource;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @return array<GalleryValue>
     */
    public function find(): array
    {
        $values = [];

        // SQL queries that will find obsolete entries:
        //
        // 1) SELECT * FROM catalog_product_entity_media_gallery
        //    WHERE media_type = 'image'
        //    AND value_id NOT IN (SELECT DISTINCT value_id FROM catalog_product_entity_media_gallery_value_to_entity);
        // 2) SELECT * FROM catalog_product_entity_media_gallery cpemg
        //    LEFT JOIN catalog_product_entity_media_gallery_value_to_entity cpemgvte
        //    ON cpemg.value_id = cpemgvte.value_id
        //    WHERE cpemgvte.value_id IS NULL AND cpemg.media_type = 'image';
        //
        // the second one is about 5 to 50 times faster on a db with
        //  218043 entries in catalog_product_entity_media_gallery and
        //  101860 entries in catalog_product_entity_media_gallery_value_to_entity

        $mediaGalleryAttr = $this->attributeRepository->get(ProductModel::ENTITY, 'media_gallery');
        $mediaGalleryId = $mediaGalleryAttr->getAttributeId();

        $fetchQuery = sprintf(
            <<<'QUERY'
SELECT cpemg.value_id, cpemg.value FROM %s cpemg
LEFT JOIN %s cpemgvte
ON cpemg.value_id = cpemgvte.value_id
WHERE cpemgvte.value_id IS NULL AND cpemg.media_type = 'image' AND cpemg.attribute_id = :media_gallery_id
QUERY,
            $this->resource->getTableName(GalleryResourceModel::GALLERY_TABLE),
            $this->resource->getTableName(GalleryResourceModel::GALLERY_VALUE_TO_ENTITY_TABLE)
        );

        $entries = $this->resource->getConnection()->fetchAll($fetchQuery, ['media_gallery_id' => $mediaGalleryId]);

        foreach ($entries as $entry) {
            $values[] = new GalleryValue((int) $entry['value_id'], $entry['value']);
        }

        return $values;
    }
}
