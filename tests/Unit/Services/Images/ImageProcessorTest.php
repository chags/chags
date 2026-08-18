<?php

use App\Data\Images\ImageProcessingOptions;
use App\Exceptions\Images\ImageProcessingException;
use App\Services\Images\ImageProcessor;

function temporaryImage(string $format, int $width = 120, int $height = 60, bool $transparent = false): string
{
    $path = tempnam(sys_get_temp_dir(), 'image-test-').'.'.$format;
    $image = imagecreatetruecolor($width, $height);

    if ($transparent) {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $color = imagecolorallocatealpha($image, 0, 0, 0, 127);
    } else {
        $color = imagecolorallocate($image, 30, 180, 90);
    }

    imagefill($image, 0, 0, $color);

    match ($format) {
        'jpg', 'jpeg' => imagejpeg($image, $path, 90),
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path, 90),
    };

    imagedestroy($image);

    return $path;
}

test('it converts supported formats to webp', function (string $format) {
    $path = temporaryImage($format);

    try {
        $result = (new ImageProcessor)->process($path, new ImageProcessingOptions);

        expect($result->mimeType)->toBe('image/webp')
            ->and($result->filename)->toEndWith('.webp')
            ->and(imagecreatefromstring($result->contents))->toBeInstanceOf(GdImage::class);
    } finally {
        unlink($path);
    }
})->with(['jpg', 'jpeg', 'png', 'webp']);

test('it resizes without changing aspect ratio', function () {
    $path = temporaryImage('png', 400, 200);

    try {
        $result = (new ImageProcessor)->process(
            $path,
            new ImageProcessingOptions(maxWidth: 100, maxHeight: 100),
        );

        expect($result->width)->toBe(100)->and($result->height)->toBe(50);
    } finally {
        unlink($path);
    }
});

test('it rejects invalid files', function () {
    $path = tempnam(sys_get_temp_dir(), 'image-test-');
    file_put_contents($path, 'not-an-image');

    try {
        (new ImageProcessor)->process($path, new ImageProcessingOptions);
    } finally {
        unlink($path);
    }
})->throws(ImageProcessingException::class);
