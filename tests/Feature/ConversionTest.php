<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
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
            'to' => 'doc',
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
            'to' => 'doc',
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
        if (! is_executable('/usr/bin/soffice')) {
            $this->markTestSkipped('LibreOffice is not installed.');
        }

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
        $this->assertStringStartsWith($signature, $download->streamedContent());
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
            'pdf to doc' => ['pdf', 'doc', "\xD0\xCF\x11\xE0"],
            'doc to pdf' => ['doc', 'pdf', '%PDF'],
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
        $docx = tempnam(sys_get_temp_dir(), 'docx');
        $docxPath = $docx.'.docx';
        rename($docx, $docxPath);
        $this->writeDocx($docxPath, 'Hello Convertal');

        if ($extension === 'docx') {
            return new UploadedFile($docxPath, 'note.docx', null, null, true);
        }

        if ($extension === 'pdf') {
            $pdfPath = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
            file_put_contents($pdfPath, $this->minimalPdf());

            return new UploadedFile($pdfPath, 'note.pdf', null, null, true);
        }

        $docPath = tempnam(sys_get_temp_dir(), 'doc').'.doc';
        $this->runSoffice($docxPath, 'doc:MS Word 97', $docPath);

        return new UploadedFile($docPath, 'note.doc', null, null, true);
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

    private function minimalPdf(): string
    {
        $objects = [
            '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj',
            '2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj',
            '3 0 obj<</Type/Page/MediaBox[0 0 300 144]/Parent 2 0 R/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj',
        ];
        $stream = 'BT /F1 18 Tf 36 80 Td (Hello Convertal) Tj ET';
        $objects[] = '4 0 obj<</Length '.strlen($stream).">>stream\n".$stream."\nendstream\nendobj";
        $objects[] = '5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj';

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= 'trailer<</Size '.(count($objects) + 1)."/Root 1 0 R>>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function runSoffice(string $input, string $filter, string $output): void
    {
        $work = sys_get_temp_dir().'/convertal-fixture-'.bin2hex(random_bytes(4));
        mkdir($work, 0755, true);
        $copy = $work.'/input.docx';
        copy($input, $copy);

        $process = new Process([
            '/usr/bin/soffice',
            '-env:UserInstallation=file://'.$work.'/profile',
            '--headless',
            '--norestore',
            '--convert-to',
            $filter,
            '--outdir',
            $work,
            $copy,
        ], $work, ['HOME' => $work, 'PATH' => getenv('PATH') ?: '/usr/bin'], null, 120);
        mkdir($work.'/profile', 0755, true);
        $process->run();
        $produced = $work.'/input.doc';

        if (! is_file($produced)) {
            $this->fail('Could not build a DOC fixture: '.$process->getErrorOutput().$process->getOutput());
        }

        copy($produced, $output);
    }
}
