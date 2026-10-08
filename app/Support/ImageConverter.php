<?php

namespace App\Support;

use App\Exceptions\ConversionException;

class ImageConverter
{
    private const int MAX_PIXELS = 40_000_000;

    public function convert(string $sourcePath, string $outputPath, string $to): void
    {
        $info = getimagesize($sourcePath);

        if ($info === false) {
            throw new ConversionException('Файл не является изображением.');
        }

        if (($info[0] * $info[1]) > self::MAX_PIXELS) {
            throw new ConversionException('Изображение слишком большое.');
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            default => throw new ConversionException('Формат изображения не поддерживается.'),
        };

        if ($image === false) {
            throw new ConversionException('Не удалось прочитать изображение.');
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->applyExifOrientation($image, $sourcePath);
        }

        $saved = match ($to) {
            'png' => $this->savePng($image, $outputPath),
            'jpg', 'jpeg' => $this->saveJpeg($image, $outputPath),
            default => throw new ConversionException('Формат изображения не поддерживается.'),
        };

        if ($saved !== true) {
            throw new ConversionException('Не удалось сохранить изображение.');
        }
    }

    private function applyExifOrientation(\GdImage $image, string $sourcePath): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($sourcePath);
        $orientation = (int) ($exif['Orientation'] ?? 0);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => null,
        };

        if ($angle === null) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    private function savePng(\GdImage $image, string $outputPath): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng($image, $outputPath, 6);
    }

    private function saveJpeg(\GdImage $image, string $outputPath): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            return false;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        return imagejpeg($canvas, $outputPath, 90);
    }
}
