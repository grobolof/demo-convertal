<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConverterPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_home_renders_the_pdf_to_docx_converter(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Convertal');
        $response->assertSee('Конвертер PDF в DOCX');
        $response->assertSee('Выберите файлы');
        $response->assertDontSee('DOC в PDF');
    }

    public function test_pair_page_renders_the_selected_conversion(): void
    {
        $response = $this->get(route('converter.show', ['from' => 'png', 'to' => 'jpeg']));

        $response->assertOk();
        $response->assertSee('Конвертер PNG в JPEG');
        $response->assertSee('Прозрачность PNG заменяется белым фоном');
    }

    public function test_unknown_pair_returns_404(): void
    {
        $this->get('/png-pdf')->assertNotFound();
        $this->get('/pdf-doc')->assertNotFound();
    }

    public function test_formats_page_lists_every_conversion(): void
    {
        $response = $this->get(route('formats.index'));

        $response->assertOk();
        $response->assertSee('PDF в DOCX');
        $response->assertSee('DOCX в PDF');
        $response->assertSee('JPG в PNG');
        $response->assertDontSee('DOC в PDF');
    }
}
