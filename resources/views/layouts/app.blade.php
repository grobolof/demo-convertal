<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="@yield('description', 'Convertal конвертирует PDF, DOCX, JPG и PNG онлайн.')">
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
                <p class="px-4">
                    © {{ date('Y') }} Convertal —
                    <a href="https://github.com/grobolof" class="font-bold text-brand hover:text-brand-dark" target="_blank" rel="noopener noreferrer">@grobolof</a>
                    (проект является демонстрацией с открытым
                    <a href="https://github.com/grobolof/demo-convertal" class="inline-flex items-center gap-1 font-bold text-brand hover:text-brand-dark" target="_blank" rel="noopener noreferrer">исходным кодом
                        <svg viewBox="0 0 16 16" class="size-3 shrink-0" fill="currentColor" aria-hidden="true">
                            <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82A7.65 7.65 0 0 1 8 4.77c.68.003 1.36.092 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0 0 16 8c0-4.42-3.58-8-8-8z"></path>
                        </svg></a>)
                </p>
            </div>
        </footer>
    </body>
</html>
