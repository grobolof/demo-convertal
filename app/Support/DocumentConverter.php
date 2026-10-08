<?php

namespace App\Support;

use App\Exceptions\ConversionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DocumentConverter
{
    public function convert(string $sourcePath, string $outputPath, string $from, string $to): void
    {
        $binary = $this->binary();
        $work = sys_get_temp_dir().'/convertal-'.bin2hex(random_bytes(8));
        $profile = $work.'/profile';
        File::makeDirectory($profile, 0755, true);

        try {
            $input = $work.'/input.'.$from;

            if (! copy($sourcePath, $input)) {
                throw new ConversionException('Не удалось подготовить документ к конвертации.');
            }

            $command = [
                $binary,
                '-env:UserInstallation=file://'.$profile,
                '--headless',
                '--nologo',
                '--nofirststartwizard',
                '--norestore',
                '--nolockcheck',
            ];

            if ($from === 'pdf') {
                $command[] = '--infilter=writer_pdf_import';
            }

            $command[] = '--convert-to';
            $command[] = $this->filter($to);
            $command[] = '--outdir';
            $command[] = $work;
            $command[] = $input;

            $process = new Process($command, $work, $this->environment($work), null, 120);
            $process->run();

            $produced = $this->producedFile($work, $input, $to);

            if (! $process->isSuccessful() || $produced === null) {
                Log::warning('LibreOffice conversion failed.', [
                    'from' => $from,
                    'to' => $to,
                    'exit' => $process->getExitCode(),
                    'output' => trim($process->getErrorOutput()."\n".$process->getOutput()),
                ]);

                throw new ConversionException('Не удалось конвертировать документ. Проверьте, что файл не повреждён.');
            }

            if (! copy($produced, $outputPath)) {
                throw new ConversionException('Не удалось сохранить результат конвертации.');
            }
        } finally {
            File::deleteDirectory($work);
        }
    }

    private function filter(string $to): string
    {
        return match ($to) {
            'pdf' => 'pdf:writer_pdf_Export',
            'docx' => 'docx:MS Word 2007 XML',
            'doc' => 'doc:MS Word 97',
            default => throw new ConversionException('Формат документа не поддерживается.'),
        };
    }

    private function producedFile(string $work, string $input, string $to): ?string
    {
        $expected = $work.'/input.'.$to;

        if (is_file($expected)) {
            return $expected;
        }

        $candidates = glob($work.'/input.*') ?: [];

        foreach ($candidates as $candidate) {
            if ($candidate !== $input && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function binary(): string
    {
        $configured = config('conversions.libreoffice_binary');
        $names = [];

        if (is_string($configured) && $configured !== '') {
            if (str_contains($configured, '/') && is_executable($configured)) {
                return $configured;
            }

            $names[] = $configured;
        }

        $names[] = 'soffice';
        $names[] = 'libreoffice';
        $finder = new ExecutableFinder;

        foreach (array_unique($names) as $name) {
            $found = $finder->find($name, null, ['/usr/bin', '/usr/lib/libreoffice/program']);

            if (is_string($found)) {
                return $found;
            }
        }

        throw new ConversionException('Конвертация документов сейчас недоступна.');
    }

    /**
     * LibreOffice locks a shared profile, so each conversion gets its own home directory.
     *
     * @return array<string, string>
     */
    private function environment(string $home): array
    {
        $path = getenv('PATH');

        return [
            'PATH' => is_string($path) && $path !== ''
                ? $path
                : '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME' => $home,
            'TMPDIR' => $home,
            'LANG' => 'C.UTF-8',
        ];
    }
}
