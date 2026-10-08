<?php

namespace App\Support;

use App\Exceptions\ConversionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

class DocumentConverter
{
    private const int PDF_DECODE_LIMIT = 64 * 1024 * 1024;

    public function convert(string $sourcePath, string $outputPath, string $from, string $to): void
    {
        try {
            match ($from.'-'.$to) {
                'docx-pdf' => $this->docxToPdf($sourcePath, $outputPath),
                'pdf-docx' => $this->pdfToDocx($sourcePath, $outputPath),
                default => throw new ConversionException('Формат документа не поддерживается.'),
            };
        } catch (ConversionException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('Document conversion failed.', [
                'from' => $from,
                'to' => $to,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new ConversionException('Не удалось конвертировать документ. Проверьте, что файл не повреждён.');
        }
    }

    private function docxToPdf(string $sourcePath, string $outputPath): void
    {
        $work = sys_get_temp_dir().'/convertal-'.bin2hex(random_bytes(8));
        File::makeDirectory($work);

        try {
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'tempDir' => $work,
                'default_font' => 'dejavusans',
                'whitelistStreamWrappers' => ['file'],
            ]);
            $pdf->WriteHTML($this->htmlForPdf($sourcePath));
            $pdf->Output($outputPath, Destination::FILE);
        } finally {
            File::deleteDirectory($work);
        }

        $header = file_get_contents($outputPath, false, null, 0, 5);

        if ($header === false || ! str_starts_with($header, '%PDF')) {
            throw new ConversionException('Не удалось конвертировать документ. Проверьте, что файл не повреждён.');
        }
    }

    private function htmlForPdf(string $sourcePath): string
    {
        $html = IOFactory::createWriter(IOFactory::load($sourcePath), 'HTML')->getContent();
        $html = preg_replace('/@page\s+[^{]+\{[^}]*\}/', '', $html) ?? $html;
        $html = preg_replace('/\sstyle=([\'"])page:[^\'"]*\1/i', '', $html) ?? $html;
        $html = preg_replace('/font-family\s*:\s*(?:\'[^\']*\'|"[^"]*"|[^;}]+)/i', 'font-family: dejavusans', $html) ?? $html;

        if (trim($html) === '') {
            throw new ConversionException('Не удалось конвертировать документ. Проверьте, что файл не повреждён.');
        }

        return $html;
    }

    private function pdfToDocx(string $sourcePath, string $outputPath): void
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);
        $section = $phpWord->addSection();

        foreach ($this->pdfParagraphs($sourcePath) as $paragraph) {
            $section->addText($paragraph);
        }

        IOFactory::createWriter($phpWord, 'Word2007')->save($outputPath);

        $zip = new ZipArchive;
        $opened = $zip->open($outputPath);
        $hasDocument = $opened === true && $zip->locateName('word/document.xml') !== false;

        if ($opened === true) {
            $zip->close();
        }

        if (! $hasDocument) {
            throw new ConversionException('Не удалось конвертировать документ. Проверьте, что файл не повреждён.');
        }
    }

    /**
     * @return list<string>
     */
    private function pdfParagraphs(string $sourcePath): array
    {
        $config = new PdfConfig;
        $config->setDecodeMemoryLimit(self::PDF_DECODE_LIMIT);
        $config->setRetainImageContent(false);

        $text = (new PdfParser([], $config))->parseFile($sourcePath)->getText();
        $lines = preg_split('/\R/u', $text) ?: [];
        $paragraphs = [];

        foreach ($lines as $line) {
            $line = mb_scrub($line, 'UTF-8');
            $line = preg_replace('/\p{C}+/u', '', $line) ?? '';
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? '');

            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        if ($paragraphs === []) {
            throw new ConversionException('В PDF не найден текст. Сканы без текстового слоя не переводятся.');
        }

        return $paragraphs;
    }
}
