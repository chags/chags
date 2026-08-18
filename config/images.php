<?php

return [
    'quality' => (int) env('IMAGE_WEBP_QUALITY', 82),
    'logo_quality' => (int) env('IMAGE_LOGO_WEBP_QUALITY', 88),
    'max_width' => (int) env('IMAGE_MAX_WIDTH', 2048),
    'max_height' => (int) env('IMAGE_MAX_HEIGHT', 2048),
    'max_bytes' => (int) env('IMAGE_MAX_BYTES', 10 * 1024 * 1024),
    'max_pixels' => (int) env('IMAGE_MAX_PIXELS', 25_000_000),
];
