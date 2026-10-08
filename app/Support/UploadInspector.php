<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class UploadInspector
{
    public function problem(UploadedFile $file, string $from): ?string
    {
        $extension = Str::lower($file->getClientOriginalExtension());
        $extensions = config('conversions.formats.'.$from.'.extensions', []);

        if (! in_array($extension, $extensions, true)) {
            return 'Расширение файла не совпадает с выбранным форматом.';
        }

        $path = $file->getRealPath();

        if ($path === false || ! is_file($path)) {
            return 'Не удалось прочитать загруженный файл.';
        }

        return match ($from) {
            'pdf' => $this->startsWith($path, '%PDF') ? null : 'Содержимое файла не похоже на PDF.',
            'png' => $this->startsWith($path, "\x89PNG") ? null : 'Содержимое файла не похоже на PNG.',
            'jpg', 'jpeg' => $this->startsWith($path, "\xFF\xD8\xFF") ? null : 'Содержимое файла не похоже на JPEG.',
            'doc' => $this->startsWith($path, "\xD0\xCF\x11\xE0") ? null : 'Содержимое файла не похоже на документ DOC.',
            'docx' => $this->isDocx($path) ? null : 'Содержимое файла не похоже на документ DOCX.',
            default => 'Формат не поддерживается.',
        };
    }

    private function startsWith(string $path, string $prefix): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $bytes = fread($handle, strlen($prefix));
        fclose($handle);

        return $bytes === $prefix;
    }

    private function isDocx(string $path): bool
    {
        if (! $this->startsWith($path, "PK\x03\x04")) {
            return false;
        }

        $zip = new ZipArchive;
        $opened = $zip->open($path);

        if ($opened !== true) {
            return false;
        }

        $hasDocument = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $hasDocument;
    }
}
