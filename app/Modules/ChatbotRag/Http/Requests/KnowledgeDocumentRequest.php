<?php

namespace App\Modules\ChatbotRag\Http\Requests;

use App\Modules\ChatbotRag\Enums\AccessScope;
use App\Modules\ChatbotRag\Enums\SourceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A knowledge document as a mall administrator writes it: typed into the form or uploaded as a
 * plain-text or Markdown file. Whether the administrator may manage this mall is decided by the
 * route's administer-mall check and again by KnowledgeService.
 */
class KnowledgeDocumentRequest extends FormRequest
{
    /**
     * Largest upload in kilobytes.
     */
    public const MAX_FILE_KB = 512;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'source_kind' => ['required', Rule::in(array_map(fn (SourceKind $kind): string => $kind->value, SourceKind::writable()))],
            'access_scope' => ['required', Rule::enum(AccessScope::class)],
            'source_reference' => ['nullable', 'string', 'max:500', 'not_regex:/^\s*system:/i'],
            'content' => ['required_without:file', 'nullable', 'string', 'max:'.(int) config('assistant.max_document_characters')],
            'file' => ['nullable', 'file', 'max:'.self::MAX_FILE_KB, 'extensions:txt,md', 'mimetypes:text/plain,text/markdown,text/x-markdown'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required_without' => __('Write the text the assistant may quote, or upload a text file.'),
            'source_reference.not_regex' => __('A source reference cannot start with "system:".'),
            'file.extensions' => __('Upload a plain-text (.txt) or Markdown (.md) file.'),
            'file.mimetypes' => __('Upload a plain-text (.txt) or Markdown (.md) file.'),
        ];
    }

    /**
     * The validated fields with the text taken from the uploaded file when there is one.
     *
     * @return array{title: string, source_kind: string, access_scope: string, content: string, source_reference: string|null}
     */
    public function document(): array
    {
        $data = $this->validated();
        $content = $this->hasFile('file') ? (string) $this->file('file')->get() : (string) ($data['content'] ?? '');

        if (! mb_check_encoding($content, 'UTF-8')) {
            throw ValidationException::withMessages(['file' => __('The file is not UTF-8 text. Save it as UTF-8 and upload it again.')]);
        }

        if (mb_strlen($content) > (int) config('assistant.max_document_characters')) {
            throw ValidationException::withMessages(['file' => __('The text is too long. Split it into several documents of at most :max characters.', ['max' => (int) config('assistant.max_document_characters')])]);
        }

        return [
            'title' => $data['title'],
            'source_kind' => $data['source_kind'],
            'access_scope' => $data['access_scope'],
            'content' => $content,
            'source_reference' => $data['source_reference'] ?? null,
        ];
    }
}
