<?php

namespace App\Services\Images;

use App\Data\Images\ImageProcessingOptions;
use App\Data\Images\ProcessedImage;
use App\Exceptions\Images\ImageProcessingException;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageProcessor
{
    public function process(UploadedFile|string $source, ?ImageProcessingOptions $options = null): ProcessedImage
    {
        $options ??= new ImageProcessingOptions(
            quality: config('images.quality'),
            maxWidth: config('images.max_width'),
            maxHeight: config('images.max_height'),
            maxBytes: config('images.max_bytes'),
            maxPixels: config('images.max_pixels'),
        );

        $path = $source instanceof UploadedFile ? $source->getRealPath() : $source;

        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw new ImageProcessingException('A imagem de origem não pode ser lida.');
        }

        $originalBytes = filesize($path);

        if (! is_int($originalBytes) || $originalBytes <= 0 || $originalBytes > $options->maxBytes) {
            throw new ImageProcessingException('A imagem excede o tamanho permitido.');
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw new ImageProcessingException('O arquivo não contém uma imagem válida.');
        }

        [$originalWidth, $originalHeight, $type] = $info;
        $format = match ($type) {
            IMAGETYPE_JPEG => 'jpeg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => throw new ImageProcessingException('O formato da imagem não é permitido.'),
        };

        if ($originalWidth <= 0 || $originalHeight <= 0 || ($originalWidth * $originalHeight) > $options->maxPixels) {
            throw new ImageProcessingException('As dimensões da imagem excedem o limite permitido.');
        }

        $image = $this->decode($path, $type);

        try {
            if ($options->autoOrient && $type === IMAGETYPE_JPEG) {
                $image = $this->orient($image, $path);
                $originalWidth = imagesx($image);
                $originalHeight = imagesy($image);
            }

            [$width, $height] = $this->targetDimensions(
                $originalWidth,
                $originalHeight,
                $options,
            );

            if ($width !== $originalWidth || $height !== $originalHeight) {
                $resized = $this->resize($image, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            ob_start();
            $encoded = imagewebp($image, null, max(1, min(100, $options->quality)));
            $contents = ob_get_clean();

            if (! $encoded || ! is_string($contents) || $contents === '') {
                throw new ImageProcessingException('Não foi possível converter a imagem para WebP.');
            }

            $validationImage = @imagecreatefromstring($contents);

            if (! $validationImage instanceof GdImage) {
                throw new ImageProcessingException('A imagem WebP gerada é inválida.');
            }

            imagedestroy($validationImage);

            return new ProcessedImage(
                contents: $contents,
                filename: Str::uuid().'.webp',
                mimeType: 'image/webp',
                width: imagesx($image),
                height: imagesy($image),
                bytes: strlen($contents),
                sha256: hash('sha256', $contents),
                originalFormat: $format,
                originalWidth: $originalWidth,
                originalHeight: $originalHeight,
                originalBytes: $originalBytes,
            );
        } finally {
            imagedestroy($image);
        }
    }

    private function decode(string $path, int $type): GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new ImageProcessingException('Não foi possível decodificar a imagem.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function orient(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = @exif_read_data($path)['Orientation'] ?? 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (! $rotated instanceof GdImage) {
            throw new ImageProcessingException('Não foi possível corrigir a orientação da imagem.');
        }

        imagedestroy($image);

        return $rotated;
    }

    /** @return array{int, int} */
    private function targetDimensions(int $width, int $height, ImageProcessingOptions $options): array
    {
        $widthRatio = $options->maxWidth ? $options->maxWidth / $width : 1;
        $heightRatio = $options->maxHeight ? $options->maxHeight / $height : 1;
        $ratio = min($widthRatio, $heightRatio);

        if ($options->preventUpscale) {
            $ratio = min(1, $ratio);
        }

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

    private function resize(GdImage $source, int $width, int $height): GdImage
    {
        $target = imagecreatetruecolor($width, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $width, $height, $transparent);

        if (! imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            imagesx($source),
            imagesy($source),
        )) {
            imagedestroy($target);
            throw new ImageProcessingException('Não foi possível redimensionar a imagem.');
        }

        return $target;
    }
}
