<?php

namespace App\Actions;

use App\Exceptions\ConversionException;
use App\Support\ConversionCatalog;
use App\Support\DocumentConverter;
use App\Support\ImageConverter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConvertUploadedFile
{
    public function __construct(
        private ConversionCatalog $catalog,
        private ImageConverter $images,
        private DocumentConverter $documents,
    ) {}

    /**
     * @return array{name: string, size: int, url: string}
     */
    public function handle(UploadedFile $file, string $from, string $to): array
    {
        $this->purgeExpired();

        $directory = 'conversions/'.(string) Str::uuid();
        $extension = Str::lower($file->getClientOriginalExtension());
        $stored = $file->storeAs($directory, 'source.'.$extension, 'local');
        $disk = Storage::disk('local');
        $outputPath = $disk->path($directory.'/result.'.$to);

        try {
            if ($this->catalog->isImage($from)) {
                $this->images->convert($disk->path($stored), $outputPath, $to);
            } else {
                $this->documents->convert($disk->path($stored), $outputPath, $from, $to);
            }
        } catch (ConversionException $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($directory);

            throw $exception;
        }

        $disk->delete($stored);

        $token = Str::random(40);
        $name = $this->downloadName($file->getClientOriginalName(), $to);

        Cache::put('conversion:'.$token, [
            'path' => $outputPath,
            'name' => $name,
            'mime' => $this->catalog->downloadMime($to),
        ], now()->addHours(2));

        return [
            'name' => $name,
            'size' => (int) filesize($outputPath),
            'url' => route('conversions.download', ['token' => $token]),
        ];
    }

    /**
     * @return array{path: string, name: string, mime: string}|null
     */
    public function find(string $token): ?array
    {
        $meta = Cache::get('conversion:'.$token);

        if (! is_array($meta) || ! isset($meta['path'], $meta['name'], $meta['mime'])) {
            return null;
        }

        $path = (string) $meta['path'];

        if (! $this->insideConversions($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => (string) $meta['name'],
            'mime' => (string) $meta['mime'],
        ];
    }

    private function insideConversions(string $path): bool
    {
        $root = realpath(Storage::disk('local')->path('conversions'));
        $real = realpath($path);

        if ($root === false || $real === false || ! is_file($real)) {
            return false;
        }

        return $real === $root || str_starts_with($real, $root.DIRECTORY_SEPARATOR);
    }

    private function purgeExpired(): void
    {
        $disk = Storage::disk('local');
        $threshold = now()->subHours(2)->getTimestamp();

        foreach ($disk->directories('conversions') as $directory) {
            if ($disk->lastModified($directory) < $threshold) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    private function downloadName(string $original, string $to): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = Str::of($base)
            ->replaceMatches('/[^\p{L}\p{N}._ -]+/u', '_')
            ->trim(' ._')
            ->limit(80, '')
            ->toString();

        if ($base === '') {
            $base = 'file';
        }

        return $base.'.'.$to;
    }
}
