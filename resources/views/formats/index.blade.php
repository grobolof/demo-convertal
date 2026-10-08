@extends('layouts.app')

@section('title', 'Форматы — Convertal')
@section('description', 'Все конвертации Convertal: PDF, DOC, DOCX, JPG, JPEG и PNG.')

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-12">
        <p class="text-sm font-semibold text-brand">Форматы</p>
        <h1 class="mt-2 text-4xl font-extrabold tracking-tight">Что можно конвертировать</h1>
        <p class="mt-4 max-w-2xl text-lg leading-7 text-muted">Документы переводятся через LibreOffice, изображения — без пережатия лишний раз. Выберите пару и загрузите файл.</p>

        <div class="mt-10 grid gap-8">
            @foreach ($groups as $group)
                <section>
                    <h2 class="text-2xl font-extrabold">{{ $group['label'] }}</h2>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($group['links'] as $link)
                            <a href="{{ $link['url'] }}" class="rounded-3xl border border-line bg-white p-5 hover:border-brand dark:border-slate-800 dark:bg-slate-900">
                                <span class="text-lg font-extrabold">{{ $link['label'] }}</span>
                                <span class="mt-2 block text-sm leading-6 text-muted">{{ $link['text'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endsection
