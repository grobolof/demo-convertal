const root = document.querySelector('[data-converter]');

if (root) {
    const catalogNode = document.getElementById('converter-catalog');
    const catalog = JSON.parse(catalogNode?.textContent ?? '{}');
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const fileInput = root.querySelector('[data-file-input]');
    const dropzone = root.querySelector('[data-dropzone]');
    const emptyState = root.querySelector('[data-empty]');
    const fileList = root.querySelector('[data-files]');
    const actions = root.querySelector('[data-actions]');
    const convertButton = root.querySelector('[data-convert]');
    const picker = root.querySelector('[data-picker]');
    const pickerTitle = root.querySelector('[data-picker-title]');
    const pickerOptions = root.querySelector('[data-picker-options]');

    const state = {
        from: root.dataset.from,
        to: root.dataset.to,
        picker: null,
        busy: false,
    };

    /** @type {Array<{id: string, file: File, status: string, message: string, downloadUrl: string, downloadName: string, resultSize: number}>} */
    const files = [];

    const extensionOf = (name) => name.split('.').pop()?.toLowerCase() ?? '';

    const formatSize = (bytes) => {
        if (bytes < 1024) {
            return `${bytes} Б`;
        }

        if (bytes < 1024 * 1024) {
            return `${(bytes / 1024).toFixed(1)} КБ`;
        }

        return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
    };

    const problemWith = (file) => {
        const format = catalog.formats[state.from];

        if (!format?.extensions.includes(extensionOf(file.name))) {
            return `Нужен файл ${format?.label ?? state.from}.`;
        }

        if (file.size === 0) {
            return 'Файл пустой.';
        }

        if (file.size > catalog.maxBytes) {
            return 'Файл больше 100 МБ.';
        }

        return '';
    };

    const setText = (name, value) => {
        const node = document.querySelector(`[data-bind="${name}"]`);

        if (node) {
            node.textContent = value ?? '';
        }
    };

    const highlightChips = () => {
        root.querySelectorAll('[data-format]').forEach((chip) => {
            const format = chip.dataset.format;

            if (format === state.to) {
                chip.dataset.state = 'target';
            } else if (format === state.from) {
                chip.dataset.state = 'source';
            } else {
                delete chip.dataset.state;
            }
        });
    };

    const fillPage = (next) => {
        document.title = next.title;
        setText('heading', next.heading);
        setText('subtitle', next.subtitle);
        setText('group', next.group_label);
        setText('crumb', next.crumb);
        setText('from-label', next.from_label);
        setText('to-label', next.to_label);

        const fromLabel = root.querySelector('[data-from-label]');
        const toLabel = root.querySelector('[data-to-label]');

        if (fromLabel) {
            fromLabel.style.color = next.source.color;
        }

        if (toLabel) {
            toLabel.style.color = next.target.color;
        }

        root.querySelectorAll('[data-feature]').forEach((node, index) => {
            node.querySelector('[data-title]').textContent = next.features[index].title;
            node.querySelector('[data-text]').textContent = next.features[index].text;
        });

        root.querySelectorAll('[data-step]').forEach((node, index) => {
            node.querySelector('[data-title]').textContent = next.steps[index].title;
            node.querySelector('[data-text]').textContent = next.steps[index].text;
        });

        root.querySelectorAll('[data-faq]').forEach((node, index) => {
            node.querySelector('[data-question]').textContent = next.faqs[index].question;
            node.querySelector('[data-answer]').textContent = next.faqs[index].answer;
        });

        setText('source-label', next.source.label);
        setText('target-label', next.target.label);
        setText('source-summary', next.source.summary);
        setText('target-summary', next.target.summary);
        setText('source-developer', next.source.developer);
        setText('target-developer', next.target.developer);
        setText('source-released', next.source.released);
        setText('target-released', next.target.released);
        setText('howto', `Как сконвертировать ${next.from_label} в ${next.to_label}`);

        const related = root.querySelector('[data-related]');
        related?.replaceChildren();

        next.related.forEach((link) => {
            const anchor = document.createElement('a');
            anchor.href = link.url;
            anchor.textContent = link.label;
            anchor.className = 'rounded-full border border-line bg-white px-3 py-1.5 text-sm font-semibold text-ink hover:border-brand hover:text-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
            related?.append(anchor);
        });

        if (fileInput) {
            fileInput.accept = next.accept;
        }

        highlightChips();
    };

    const selectPair = (from, to, push = true) => {
        const next = catalog.pages[`${from}-${to}`];

        if (!next) {
            return;
        }

        state.from = from;
        state.to = to;
        root.dataset.from = from;
        root.dataset.to = to;
        closePicker();
        fillPage(next);
        revalidateFiles();

        if (push) {
            history.pushState({ from, to }, '', next.url);
        }
    };

    const closePicker = () => {
        state.picker = null;

        if (picker) {
            picker.hidden = true;
        }
    };

    const openPicker = (side) => {
        state.picker = side;

        if (!picker || !pickerOptions || !pickerTitle) {
            return;
        }

        pickerTitle.textContent = side === 'from' ? 'Исходный формат' : 'Конечный формат';
        pickerOptions.replaceChildren();

        Object.entries(catalog.formats).forEach(([format, meta]) => {
            const allowed = side === 'from'
                ? Boolean(catalog.pairs[format]?.length)
                : (catalog.pairs[state.from] ?? []).includes(format);
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = meta.label;
            button.disabled = !allowed;
            button.className = allowed
                ? 'format-chip'
                : 'format-chip cursor-not-allowed opacity-40';

            if (allowed) {
                button.addEventListener('click', () => {
                    if (side === 'from') {
                        const targets = catalog.pairs[format];
                        const nextTo = targets.includes(state.to) ? state.to : targets[0];
                        selectPair(format, nextTo);
                    } else {
                        selectPair(state.from, format);
                    }

                    closePicker();
                });
            }

            pickerOptions.append(button);
        });

        picker.hidden = false;
    };

    const renderFiles = () => {
        const hasFiles = files.length > 0;
        emptyState?.classList.toggle('hidden', hasFiles);
        fileList?.classList.toggle('hidden', !hasFiles);
        actions?.classList.toggle('hidden', !hasFiles);
        fileList?.replaceChildren();

        files.forEach((item) => {
            const row = document.createElement('div');
            row.className = 'flex flex-col gap-3 rounded-2xl border border-line bg-canvas px-4 py-3 sm:flex-row sm:items-center dark:border-slate-700 dark:bg-slate-900';

            const info = document.createElement('div');
            info.className = 'min-w-0 flex-1';

            const name = document.createElement('p');
            name.className = 'truncate font-semibold';
            name.textContent = item.status === 'done' ? item.downloadName : item.file.name;

            const meta = document.createElement('p');
            meta.className = 'mt-1 text-sm text-muted';
            const size = item.status === 'done' ? item.resultSize : item.file.size;
            const status = {
                queued: 'Ожидает',
                converting: 'Конвертация…',
                done: 'Готово',
                error: item.message,
            }[item.status] ?? '';
            meta.textContent = `${formatSize(size)} · ${catalog.formats[state.from].label} → ${catalog.formats[state.to].label} · ${status}`;

            if (item.status === 'error') {
                meta.classList.add('text-red-600');
            }

            info.append(name, meta);

            const controls = document.createElement('div');
            controls.className = 'flex items-center gap-2';

            if (item.status === 'done') {
                const download = document.createElement('a');
                download.href = item.downloadUrl;
                download.textContent = 'Скачать';
                download.className = 'inline-flex h-10 items-center rounded-xl bg-good px-4 text-sm font-bold text-white hover:bg-good-dark';
                controls.append(download);
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Удалить';
            remove.className = 'inline-flex h-10 items-center rounded-xl border border-line px-3 text-sm font-semibold text-muted hover:text-ink dark:border-slate-700';
            remove.addEventListener('click', () => {
                const index = files.findIndex((file) => file.id === item.id);

                if (index >= 0) {
                    files.splice(index, 1);
                    renderFiles();
                }
            });
            controls.append(remove);
            row.append(info, controls);
            fileList?.append(row);
        });

        const pending = files.some((item) => item.status === 'queued');

        if (convertButton) {
            convertButton.disabled = state.busy || !pending;
        }
    };

    const revalidateFiles = () => {
        files.forEach((item) => {
            if (item.status === 'done' || item.status === 'converting') {
                return;
            }

            const problem = problemWith(item.file);
            item.status = problem ? 'error' : 'queued';
            item.message = problem;
        });

        renderFiles();
    };

    const addFiles = (list) => {
        Array.from(list).forEach((file) => {
            const problem = problemWith(file);
            files.push({
                id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                file,
                status: problem ? 'error' : 'queued',
                message: problem,
                downloadUrl: '',
                downloadName: '',
                resultSize: 0,
            });
        });

        renderFiles();
    };

    const errorMessage = async (response) => {
        if (response.status === 429) {
            return 'Слишком много конвертаций. Подождите минуту и попробуйте снова.';
        }

        const payload = await response.json().catch(() => ({}));

        return payload.errors?.file?.[0]
            || payload.errors?.to?.[0]
            || payload.message
            || 'Не удалось конвертировать файл.';
    };

    const convertAll = async () => {
        const pending = files.filter((item) => item.status === 'queued');

        if (pending.length === 0 || state.busy) {
            return;
        }

        state.busy = true;
        renderFiles();

        for (const item of pending) {
            item.status = 'converting';
            item.message = '';
            renderFiles();

            const body = new FormData();
            body.append('file', item.file);
            body.append('from', state.from);
            body.append('to', state.to);

            try {
                const response = await fetch(catalog.endpoints.store, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body,
                });

                if (!response.ok) {
                    item.status = 'error';
                    item.message = await errorMessage(response);
                    continue;
                }

                const payload = await response.json();
                item.status = 'done';
                item.downloadUrl = payload.url;
                item.downloadName = payload.name;
                item.resultSize = payload.size;
            } catch {
                item.status = 'error';
                item.message = 'Не удалось связаться с сервером.';
            }
        }

        state.busy = false;
        renderFiles();
    };

    root.querySelector('[data-browse]')?.addEventListener('click', () => fileInput?.click());
    root.querySelector('[data-add]')?.addEventListener('click', () => fileInput?.click());
    fileInput?.addEventListener('change', () => {
        addFiles(fileInput.files ?? []);
        fileInput.value = '';
    });

    let dragDepth = 0;

    dropzone?.addEventListener('dragenter', (event) => {
        event.preventDefault();
        dragDepth += 1;
        dropzone.classList.add('ring-2', 'ring-brand');
    });

    dropzone?.addEventListener('dragover', (event) => event.preventDefault());

    dropzone?.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);

        if (dragDepth === 0) {
            dropzone.classList.remove('ring-2', 'ring-brand');
        }
    });

    dropzone?.addEventListener('drop', (event) => {
        event.preventDefault();
        dragDepth = 0;
        dropzone.classList.remove('ring-2', 'ring-brand');
        addFiles(event.dataTransfer?.files ?? []);
    });

    document.addEventListener('dragover', (event) => event.preventDefault());
    document.addEventListener('drop', (event) => {
        if (!dropzone?.contains(event.target)) {
            event.preventDefault();
        }
    });

    convertButton?.addEventListener('click', convertAll);

    root.querySelectorAll('[data-open-picker]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const side = button.dataset.openPicker;

            if (state.picker === side) {
                closePicker();

                return;
            }

            openPicker(side);
        });
    });

    document.addEventListener('click', (event) => {
        if (picker && !picker.contains(event.target)) {
            closePicker();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closePicker();
        }
    });

    root.querySelectorAll('[data-format]').forEach((chip) => {
        chip.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            const format = chip.dataset.format;
            const targets = catalog.pairs[state.from] ?? [];

            if (targets.includes(format)) {
                selectPair(state.from, format);

                return;
            }

            const nextTargets = catalog.pairs[format] ?? [];

            if (nextTargets.length === 0) {
                return;
            }

            selectPair(format, nextTargets.includes(state.to) ? state.to : nextTargets[0]);
        });
    });

    window.addEventListener('popstate', (event) => {
        const from = event.state?.from;
        const to = event.state?.to;

        if (from && to) {
            selectPair(from, to, false);
        }
    });

    history.replaceState({ from: state.from, to: state.to }, '', window.location.href);
    highlightChips();
}
