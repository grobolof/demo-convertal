<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use PHPUnit\Framework\Attributes\DataProvider;
use Smalot\PdfParser\Parser;
use Tests\TestCase;
use ZipArchive;

class ConversionTest extends TestCase
{
    public function test_missing_file_returns_422_and_asks_to_choose_a_file(): void
    {
        $response = $this->postConversion([
            'from' => 'png',
            'to' => 'jpg',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'file' => 'Выберите файл.',
        ]);
    }

    public function test_unsupported_pair_returns_422(): void
    {
        Storage::fake('local');

        $response = $this->postConversion([
            'file' => UploadedFile::fake()->image('photo.png'),
            'from' => 'png',
            'to' => 'pdf',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'to' => 'Эта пара форматов не поддерживается.',
        ]);
        $this->assertSame([], Storage::disk('local')->allFiles('conversions'));
    }

    public function test_wrong_extension_returns_422(): void
    {
        Storage::fake('local');

        $response = $this->postConversion([
            'file' => UploadedFile::fake()->image('photo.png'),
            'from' => 'pdf',
            'to' => 'docx',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'file' => 'Расширение файла не совпадает с выбранным форматом.',
        ]);
    }

    public function test_file_with_mismatched_contents_returns_422(): void
    {
        Storage::fake('local');

        $response = $this->postConversion([
            'file' => UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf'),
            'from' => 'pdf',
            'to' => 'docx',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors([
            'file' => 'Содержимое файла не похоже на PDF.',
        ]);
    }

    public function test_unknown_download_token_returns_404(): void
    {
        $this->get('/conversions/'.str_repeat('a', 40))->assertNotFound();
    }

    public function test_download_name_strips_markup_from_the_original_filename(): void
    {
        Storage::fake('local');

        $response = $this->postConversion([
            'file' => UploadedFile::fake()->image('photo<script>.png'),
            'from' => 'png',
            'to' => 'jpg',
        ]);

        $response->assertOk();
        $name = $response->json('name');
        $this->assertIsString($name);
        $this->assertStringEndsWith('.jpg', $name);
        $this->assertStringNotContainsString('<', $name);
    }

    #[DataProvider('imageConversions')]
    public function test_image_upload_returns_a_converted_download(string $from, string $to, int $imageType): void
    {
        Storage::fake('local');

        $response = $this->postConversion([
            'file' => UploadedFile::fake()->image('photo.'.$from, 16, 16),
            'from' => $from,
            'to' => $to,
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'photo.'.$to);

        $stored = Storage::disk('local')->allFiles('conversions');
        $this->assertCount(1, $stored);
        $this->assertStringEndsWith('.'.$to, $stored[0]);

        $download = $this->get($response->json('url'));
        $download->assertOk();
        $download->assertDownload('photo.'.$to);

        $info = getimagesizefromstring($download->streamedContent());
        $this->assertIsArray($info);
        $this->assertSame($imageType, $info[2]);
    }

    #[DataProvider('documentConversions')]
    public function test_document_upload_returns_a_converted_download(string $from, string $to, string $signature): void
    {
        Storage::fake('local');

        $response = $this->postConversion([
            'file' => $this->document($from),
            'from' => $from,
            'to' => $to,
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'note.'.$to);

        $download = $this->get($response->json('url'));
        $download->assertOk();
        $download->assertDownload('note.'.$to);

        $content = $download->streamedContent();
        $this->assertStringStartsWith($signature, $content);
        $this->assertStringContainsString('Привет', $this->textOf($content, $to));
    }

    public function test_pdf_without_text_returns_422(): void
    {
        Storage::fake('local');

        $response = $this->postConversion([
            'file' => $this->pdf(''),
            'from' => 'pdf',
            'to' => 'docx',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'В PDF не найден текст. Сканы без текстового слоя не переводятся.');
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function imageConversions(): array
    {
        return [
            'png to jpg' => ['png', 'jpg', IMAGETYPE_JPEG],
            'png to jpeg' => ['png', 'jpeg', IMAGETYPE_JPEG],
            'jpg to png' => ['jpg', 'png', IMAGETYPE_PNG],
            'jpeg to png' => ['jpeg', 'png', IMAGETYPE_PNG],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function documentConversions(): array
    {
        return [
            'docx to pdf' => ['docx', 'pdf', '%PDF'],
            'pdf to docx' => ['pdf', 'docx', "PK\x03\x04"],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postConversion(array $payload): TestResponse
    {
        $token = 'test-csrf-token';

        return $this->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->postJson(route('conversions.store'), $payload);
    }

    private function document(string $extension): UploadedFile
    {
        if ($extension === 'docx') {
            $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
            $this->writeDocx($path, 'Привет, Convertal');

            return new UploadedFile($path, 'note.docx', null, null, true);
        }

        return $this->pdf('Привет, Convertal');
    }

    private function pdf(string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
        $work = sys_get_temp_dir().'/convertal-fixture-'.bin2hex(random_bytes(4));
        mkdir($work);
        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $work,
            'default_font' => 'dejavusans',
        ]);
        $pdf->WriteHTML('<p>'.htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>');
        $pdf->Output($path, Destination::FILE);

        return new UploadedFile($path, 'note.pdf', null, null, true);
    }

    private function textOf(string $content, string $extension): string
    {
        if ($extension === 'pdf') {
            return (new Parser)->parseContent($content)->getText();
        }

        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, $content);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return is_string($xml) ? $xml : '';
    }

    private function writeDocx(string $path, string $text): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);
        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);
        $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t>{$text}</w:t></w:r></w:p></w:body>
</w:document>
XML);
        $zip->close();
    }
}
