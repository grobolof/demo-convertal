<?php

namespace App\Http\Controllers;

use App\Support\ConversionCatalog;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ConverterController extends Controller
{
    public function __construct(private ConversionCatalog $catalog) {}

    public function index(): View
    {
        return $this->show('pdf', 'docx');
    }

    public function show(string $from, string $to): View
    {
        $from = Str::lower($from);
        $to = Str::lower($to);

        abort_unless($this->catalog->supports($from, $to), 404);

        return view('converter.show', [
            'page' => $this->catalog->page($from, $to),
            'catalog' => $this->catalog->browserCatalog(),
        ]);
    }

    public function formats(): View
    {
        return view('formats.index', [
            'groups' => $this->catalog->groupedLinks(),
        ]);
    }
}
