<?php

declare(strict_types=1);

namespace Baldwin\ImageCleanup\DataObject;

class GalleryValue
{
    private $valueId;
    private $value;

    public function __construct(int $valueId, string $value)
    {
        $this->valueId = $valueId;
        $this->value   = $value;
    }

    public function __toString()
    {
        return sprintf(
            '[valueId %s] %s',
            $this->valueId,
            $this->value
        );
    }
}
