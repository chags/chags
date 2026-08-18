<?php

namespace App\Data\Images;

final readonly class ProcessedImage
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $mimeType,
        public int $width,
        public int $height,
        public int $bytes,
        public string $sha256,
        public string $originalFormat,
        public int $originalWidth,
        public int $originalHeight,
        public int $originalBytes,
    ) {}
}
