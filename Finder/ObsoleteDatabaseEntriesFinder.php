<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\Finder;

use Baldwin\ImageCleanup\DataObject\GalleryValue;

class ObsoleteDatabaseEntriesFinder
{
    public function __construct()
    {
        // TODO
    }

    /**
     * @return array<GalleryValue>
     */
    public function find(): array
    {
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

        // TODO: add extra filter on attribute_id being id from 'media_gallery'

        // TODO
        return [];
    }
}
