<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="@yield('description', 'Convertal конвертирует PDF, DOC, DOCX, JPG и PNG онлайн.')">
        <title>@yield('title', 'Convertal')</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-canvas text-ink antialiased dark:bg-slate-950 dark:text-slate-100">
        <header class="sticky top-0 z-40 border-b border-line/80 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-950/90">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                    <span class="grid size-9 place-items-center rounded-xl bg-brand text-white shadow-lg shadow-brand/30">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M7 7h11l-3-3"></path>
                            <path d="M18 7l-3 3"></path>
                            <path d="M17 17H6l3 3"></path>
                            <path d="M6 17l3-3"></path>
                        </svg>
                    </span>
                    <span class="text-xl font-extrabold tracking-tight">Convertal</span>
                </a>
                <nav class="flex items-center gap-1 text-sm font-semibold sm:gap-2">
                    <a
                        href="{{ route('home') }}"
                        @class([
                            'rounded-full px-3 py-2',
                            'bg-brand/10 text-brand' => request()->routeIs('home', 'converter.show'),
                            'text-muted hover:text-ink dark:hover:text-white' => ! request()->routeIs('home', 'converter.show'),
                        ])
                    >Конвертер</a>
                    <a
                        href="{{ route('formats.index') }}"
                        @class([
                            'rounded-full px-3 py-2',
                            'bg-brand/10 text-brand' => request()->routeIs('formats.index'),
                            'text-muted hover:text-ink dark:hover:text-white' => ! request()->routeIs('formats.index'),
                        ])
                    >Форматы</a>
                </nav>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="border-t border-line bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-3">
                <div>
                    <p class="text-lg font-extrabold">Convertal</p>
                    <p class="mt-2 max-w-xs text-sm leading-6 text-muted">Онлайн-конвертер документов и изображений. Файлы обрабатываются на сервере и затем удаляются.</p>
                </div>
                <div>
                    <p class="text-sm font-bold">Разделы</p>
                    <div class="mt-3 flex flex-col gap-2 text-sm text-muted">
                        <a href="{{ route('home') }}" class="hover:text-brand">Конвертер</a>
                        <a href="{{ route('formats.index') }}" class="hover:text-brand">Все форматы</a>
                        <a href="{{ route('converter.show', ['from' => 'pdf', 'to' => 'docx']) }}" class="hover:text-brand">PDF в DOCX</a>
                        <a href="{{ route('converter.show', ['from' => 'png', 'to' => 'jpg']) }}" class="hover:text-brand">PNG в JPG</a>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-bold">Ограничения</p>
                    <p class="mt-3 text-sm leading-6 text-muted">До 100 МБ на файл. Готовые файлы хранятся два часа и скачиваются по индивидуальной ссылке.</p>
                </div>
            </div>
            <div class="border-t border-line py-4 text-center text-xs text-muted dark:border-slate-800">
                © {{ date('Y') }} Convertal
            </div>
        </footer>
    </body>
</html>
