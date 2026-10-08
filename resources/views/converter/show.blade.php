@extends('layouts.app')

@section('title', $page['title'])
@section('description', $page['subtitle'])

@section('content')
    <div data-converter data-from="{{ $page['from'] }}" data-to="{{ $page['to'] }}">
        <div class="mx-auto max-w-6xl px-4 pb-20 pt-8 sm:pt-12">
            <nav class="flex flex-wrap items-center gap-2 text-sm text-muted">
                <a href="{{ route('home') }}" class="hover:text-brand">Главная</a>
                <span aria-hidden="true">/</span>
                <span>Конвертер <span data-bind="group">{{ $page['group_label'] }}</span></span>
                <span aria-hidden="true">/</span>
                <span class="font-semibold text-ink dark:text-white" data-bind="crumb">{{ $page['crumb'] }}</span>
            </nav>

            <div class="mx-auto mt-6 max-w-3xl text-center">
                <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl" data-bind="heading">{{ $page['heading'] }}</h1>
                <p class="mt-4 text-lg leading-7 text-muted" data-bind="subtitle">{{ $page['subtitle'] }}</p>
            </div>

            <section
                data-dropzone
                class="relative mx-auto mt-8 max-w-3xl rounded-[28px] border border-line bg-white px-4 py-8 shadow-[0_24px_70px_-36px_rgba(22,70,140,0.45)] sm:px-8 dark:border-slate-800 dark:bg-slate-900"
            >
                <input data-file-input class="hidden" type="file" multiple accept="{{ $page['accept'] }}">

                <div data-empty class="text-center">
                    <button
                        type="button"
                        data-browse
                        class="inline-flex h-14 items-center justify-center rounded-2xl bg-brand px-8 text-lg font-bold text-white shadow-lg shadow-brand/30 hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                    >
                        Выберите файлы
                    </button>
                    <p class="mt-4 text-sm text-muted">Перетащите файлы сюда. 100 МБ — максимальный размер файла.</p>
                    <noscript>
                        <p class="mt-3 text-sm text-muted">Загрузка файлов работает с включённым JavaScript. Ссылки на форматы открываются и без него.</p>
                    </noscript>
                </div>

                <div data-files class="hidden space-y-3"></div>

                <div class="mt-8 grid gap-4 sm:grid-cols-2">
                    @foreach (['document' => 'Документы', 'image' => 'Изображения'] as $groupKey => $groupLabel)
                        <div>
                            <p class="text-xs font-bold tracking-[0.14em] text-muted uppercase">{{ $groupLabel }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($catalog['formats'] as $key => $format)
                                    @if ($format['group'] === $groupKey)
                                        <a
                                            href="{{ route('converter.show', ['from' => $key, 'to' => $catalog['pairs'][$key][0]]) }}"
                                            data-format="{{ $key }}"
                                            @if ($key === $page['to']) data-state="target"
                                            @elseif ($key === $page['from']) data-state="source" @endif
                                            class="format-chip"
                                        >{{ $format['label'] }}</a>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="relative mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row sm:gap-6">
                    <button
                        type="button"
                        data-open-picker="from"
                        class="flex min-w-40 flex-col items-center rounded-3xl border border-line bg-white px-8 py-5 shadow-sm hover:border-brand dark:border-slate-700 dark:bg-slate-950"
                    >
                        <span class="text-[11px] font-bold tracking-[0.16em] text-muted uppercase">Из</span>
                        <span data-from-label data-bind="from-label" class="mt-1 text-4xl font-extrabold tracking-wide uppercase" style="color: {{ $page['source']['color'] }}">{{ $page['from_label'] }}</span>
                    </button>
                    <span class="grid size-12 place-items-center rounded-full bg-canvas text-sm font-extrabold text-muted dark:bg-slate-800">в</span>
                    <button
                        type="button"
                        data-open-picker="to"
                        class="flex min-w-40 flex-col items-center rounded-3xl border border-line bg-white px-8 py-5 shadow-sm hover:border-brand dark:border-slate-700 dark:bg-slate-950"
                    >
                        <span class="text-[11px] font-bold tracking-[0.16em] text-muted uppercase">В</span>
                        <span data-to-label data-bind="to-label" class="mt-1 text-4xl font-extrabold tracking-wide uppercase" style="color: {{ $page['target']['color'] }}">{{ $page['to_label'] }}</span>
                    </button>
                    <div
                        data-picker
                        hidden
                        class="absolute top-full left-1/2 z-20 mt-3 w-[min(100%,28rem)] -translate-x-1/2 rounded-3xl border border-line bg-white p-4 text-left shadow-2xl dark:border-slate-700 dark:bg-slate-950"
                    >
                        <p data-picker-title class="text-sm font-bold">Формат</p>
                        <div data-picker-options class="mt-3 flex flex-wrap gap-2"></div>
                    </div>
                </div>

                <div data-actions class="mt-8 hidden text-center">
                    <button
                        type="button"
                        data-add
                        class="mb-3 text-sm font-semibold text-brand hover:text-brand-dark"
                    >Добавить ещё файлы</button>
                    <div>
                        <button
                            type="button"
                            data-convert
                            class="inline-flex h-14 items-center justify-center rounded-2xl bg-good px-8 text-lg font-bold text-white shadow-lg shadow-good/30 hover:bg-good-dark disabled:cursor-not-allowed disabled:opacity-50"
                        >Конвертировать</button>
                    </div>
                </div>
            </section>

            <section class="mt-14 grid gap-4 md:grid-cols-3">
                @foreach ($page['features'] as $feature)
                    <article data-feature class="rounded-3xl border border-line bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                        <div class="grid size-12 place-items-center rounded-2xl bg-brand/10 text-brand">
                            @if ($feature['icon'] === 'speed')
                                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13 3 5 14h7l-1 7 8-11h-7l1-7Z" stroke-linejoin="round"></path></svg>
                            @elseif ($feature['icon'] === 'lock')
                                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"></rect><path d="M8 11V8a4 4 0 0 1 8 0v3"></path></svg>
                            @else
                                <svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h11M4 12h16M4 17h9" stroke-linecap="round"></path></svg>
                            @endif
                        </div>
                        <h2 class="mt-4 text-lg font-extrabold" data-title>{{ $feature['title'] }}</h2>
                        <p class="mt-2 text-sm leading-6 text-muted" data-text>{{ $feature['text'] }}</p>
                    </article>
                @endforeach
            </section>

            <section class="mt-16">
                <h2 class="text-3xl font-extrabold tracking-tight" data-bind="howto">Как сконвертировать {{ $page['from_label'] }} в {{ $page['to_label'] }}</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    @foreach ($page['steps'] as $index => $step)
                        <article data-step class="rounded-3xl bg-white p-6 dark:bg-slate-900">
                            <span class="grid size-9 place-items-center rounded-full bg-brand text-sm font-extrabold text-white">{{ $index + 1 }}</span>
                            <h3 class="mt-4 text-lg font-extrabold" data-title>{{ $step['title'] }}</h3>
                            <p class="mt-2 text-sm leading-6 text-muted" data-text>{{ $step['text'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="mt-16">
                <h2 class="text-3xl font-extrabold tracking-tight">О форматах</h2>
                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    @foreach (['source' => $page['source'], 'target' => $page['target']] as $side => $format)
                        <article class="rounded-3xl border border-line bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-2xl font-extrabold" data-bind="{{ $side }}-label">{{ $format['label'] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-muted" data-bind="{{ $side }}-summary">{{ $format['summary'] }}</p>
                            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <dt class="text-muted">Разработчик</dt>
                                    <dd class="font-semibold" data-bind="{{ $side }}-developer">{{ $format['developer'] }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Год</dt>
                                    <dd class="font-semibold" data-bind="{{ $side }}-released">{{ $format['released'] }}</dd>
                                </div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="mt-16">
                <h2 class="text-3xl font-extrabold tracking-tight">Частые вопросы</h2>
                <div class="mt-4 divide-y divide-line rounded-3xl border border-line bg-white px-5 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    @foreach ($page['faqs'] as $faq)
                        <details data-faq class="group py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left font-bold">
                                <span data-question>{{ $faq['question'] }}</span>
                                <span class="text-xl leading-none text-brand transition group-open:rotate-45" aria-hidden="true">+</span>
                            </summary>
                            <p class="mt-3 max-w-3xl text-sm leading-6 text-muted" data-answer>{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>

            <section class="mt-16">
                <h2 class="text-3xl font-extrabold tracking-tight">Связанные конвертации</h2>
                <div data-related class="mt-6 flex flex-wrap gap-2">
                    @foreach ($page['related'] as $link)
                        <a href="{{ $link['url'] }}" class="rounded-full border border-line bg-white px-3 py-1.5 text-sm font-semibold text-ink hover:border-brand hover:text-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </section>
        </div>
        <script type="application/json" id="converter-catalog">@json($catalog)</script>
    </div>
@endsection
