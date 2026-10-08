<?php

namespace App\Http\Requests;

use App\Support\UploadInspector;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => Str::lower((string) $this->input('from')),
            'to' => Str::lower((string) $this->input('to')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(int) config('conversions.max_kilobytes')],
            'from' => ['required', 'string', Rule::in(array_keys(config('conversions.pairs')))],
            'to' => ['required', 'string', Rule::in(array_keys(config('conversions.formats')))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $megabytes = (int) (config('conversions.max_kilobytes') / 1024);

        return [
            'file.required' => 'Выберите файл.',
            'file.file' => 'Не удалось загрузить файл.',
            'file.max' => 'Файл больше '.$megabytes.' МБ.',
            'from.required' => 'Выберите исходный формат.',
            'from.in' => 'Исходный формат не поддерживается.',
            'to.required' => 'Выберите конечный формат.',
            'to.in' => 'Конечный формат не поддерживается.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = $this->string('from')->toString();
                $to = $this->string('to')->toString();
                $targets = config('conversions.pairs.'.$from, []);

                if (! in_array($to, $targets, true)) {
                    $validator->errors()->add('to', 'Эта пара форматов не поддерживается.');

                    return;
                }

                $problem = (new UploadInspector)->problem($this->file('file'), $from);

                if ($problem !== null) {
                    $validator->errors()->add('file', $problem);
                }
            },
        ];
    }
}
