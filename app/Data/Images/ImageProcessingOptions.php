<?php

namespace App\Data\Images;

final readonly class ImageProcessingOptions
{
    public function __construct(
        public int $quality = 82,
        public ?int $maxWidth = 2048,
        public ?int $maxHeight = 2048,
        public bool $preventUpscale = true,
        public bool $autoOrient = true,
        public int $maxBytes = 10_485_760,
        public int $maxPixels = 25_000_000,
    ) {}

    public static function logo(): self
    {
        return new self(
            quality: config('images.logo_quality'),
            maxWidth: 2048,
            maxHeight: 2048,
            maxBytes: 2 * 1024 * 1024,
            maxPixels: config('images.max_pixels'),
        );
    }
}
