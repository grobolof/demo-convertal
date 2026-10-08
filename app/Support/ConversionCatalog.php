<?php

namespace App\Support;

class ConversionCatalog
{
    /**
     * @return array<string, list<string>>
     */
    public function pairs(): array
    {
        return config('conversions.pairs');
    }

    public function supports(string $from, string $to): bool
    {
        return in_array($to, $this->targets($from), true);
    }

    /**
     * @return list<string>
     */
    public function targets(string $from): array
    {
        $targets = config('conversions.pairs.'.$from, []);

        return is_array($targets) ? array_values($targets) : [];
    }

    public function isImage(string $format): bool
    {
        return config('conversions.formats.'.$format.'.group') === 'image';
    }

    public function downloadMime(string $format): string
    {
        return (string) config('conversions.formats.'.$format.'.download_mime');
    }

    public function label(string $format): string
    {
        return (string) config('conversions.formats.'.$format.'.label', strtoupper($format));
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $from, string $to): array
    {
        $source = $this->format($from);
        $target = $this->format($to);
        $key = $from.'-'.$to;
        $group = (string) $source['group'];

        return [
            'from' => $from,
            'to' => $to,
            'from_label' => $source['label'],
            'to_label' => $target['label'],
            'accept' => $this->accept($from),
            'url' => route('converter.show', ['from' => $from, 'to' => $to]),
            'title' => $source['label'].' в '.$target['label'].' — Convertal',
            'heading' => 'Конвертер '.$source['label'].' в '.$target['label'],
            'subtitle' => 'Конвертировать '.$source['label'].' в '.$target['label'].' онлайн — бесплатно',
            'group' => $group,
            'group_label' => (string) config('conversions.groups.'.$group),
            'crumb' => $source['label'].' в '.$target['label'],
            'source' => $source,
            'target' => $target,
            'features' => [
                [
                    'icon' => 'compat',
                    'title' => 'Нужный формат',
                    'text' => (string) config('conversions.reasons.'.$key),
                ],
                [
                    'icon' => 'speed',
                    'title' => 'Быстрая обработка',
                    'text' => 'Файлы конвертируются на сервере. Ставить офис или графический редактор не нужно.',
                ],
                [
                    'icon' => 'lock',
                    'title' => 'Конфиденциальность',
                    'text' => 'Исходник удаляется сразу после конвертации. Результат стирается в течение двух часов.',
                ],
            ],
            'steps' => [
                [
                    'title' => 'Выберите файлы',
                    'text' => 'Загрузите '.$source['label'].' с компьютера или перетащите файлы в область на странице. Можно добавить несколько сразу.',
                ],
                [
                    'title' => 'Проверьте формат',
                    'text' => 'Сейчас выбран перевод в '.$target['label'].'. Формат можно сменить до запуска конвертации.',
                ],
                [
                    'title' => 'Скачайте результат',
                    'text' => 'Нажмите «Конвертировать» и сохраните готовый '.$target['label'].'-файл.',
                ],
            ],
            'faqs' => [
                [
                    'question' => 'Зачем переводить '.$source['label'].' в '.$target['label'].'?',
                    'answer' => (string) config('conversions.reasons.'.$key),
                ],
                [
                    'question' => 'Что открывает '.$target['label'].'?',
                    'answer' => (string) config('conversions.opens_with.'.$to),
                ],
                [
                    'question' => 'Конвертация бесплатная?',
                    'answer' => 'Да. Конвертация на Convertal бесплатная, регистрация не нужна.',
                ],
                [
                    'question' => 'Сохранится ли оформление?',
                    'answer' => (string) config('conversions.layout_notes.'.$key),
                ],
                [
                    'question' => 'Можно ли конвертировать несколько файлов?',
                    'answer' => 'Да. Добавьте несколько файлов — каждый будет сконвертирован отдельно, и его можно скачать своим файлом.',
                ],
                [
                    'question' => 'Это безопасно?',
                    'answer' => 'Исходный файл удаляется сразу после обработки. Готовый файл хранится не дольше двух часов и доступен только по уникальной ссылке.',
                ],
            ],
            'related' => $this->related($from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function browserCatalog(): array
    {
        $pages = [];

        foreach ($this->pairs() as $from => $targets) {
            foreach ($targets as $to) {
                $pages[$from.'-'.$to] = $this->page($from, $to);
            }
        }

        $formats = [];

        foreach (array_keys(config('conversions.formats')) as $format) {
            $formats[$format] = $this->format($format);
        }

        return [
            'maxBytes' => (int) config('conversions.max_kilobytes') * 1024,
            'endpoints' => [
                'store' => route('conversions.store'),
            ],
            'formats' => $formats,
            'pairs' => $this->pairs(),
            'pages' => $pages,
        ];
    }

    /**
     * @return list<array{label: string, links: list<array{label: string, url: string, text: string}>}>
     */
    public function groupedLinks(): array
    {
        $groups = [];

        foreach (config('conversions.groups') as $key => $label) {
            $groups[$key] = [
                'label' => $label,
                'links' => [],
            ];
        }

        foreach ($this->pairs() as $from => $targets) {
            $group = (string) config('conversions.formats.'.$from.'.group');

            foreach ($targets as $to) {
                $groups[$group]['links'][] = [
                    'label' => $this->label($from).' в '.$this->label($to),
                    'url' => route('converter.show', ['from' => $from, 'to' => $to]),
                    'text' => (string) config('conversions.reasons.'.$from.'-'.$to),
                ];
            }
        }

        return array_values($groups);
    }

    /**
     * @return array{label: string, group: string, color: string, extensions: list<string>, developer: string, released: string, summary: string}
     */
    private function format(string $format): array
    {
        $data = config('conversions.formats.'.$format);

        return [
            'label' => (string) $data['label'],
            'group' => (string) $data['group'],
            'color' => (string) $data['color'],
            'extensions' => array_values($data['extensions']),
            'developer' => (string) $data['developer'],
            'released' => (string) $data['released'],
            'summary' => (string) $data['summary'],
        ];
    }

    private function accept(string $from): string
    {
        return collect(config('conversions.formats.'.$from.'.extensions', []))
            ->map(fn (string $extension): string => '.'.$extension)
            ->implode(',');
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function related(string $from, string $to): array
    {
        $links = [];

        foreach ($this->pairs() as $source => $targets) {
            foreach ($targets as $target) {
                if ($source === $from && $target === $to) {
                    continue;
                }

                $links[] = [
                    'label' => $this->label($source).' в '.$this->label($target),
                    'url' => route('converter.show', ['from' => $source, 'to' => $target]),
                ];
            }
        }

        return $links;
    }
}
