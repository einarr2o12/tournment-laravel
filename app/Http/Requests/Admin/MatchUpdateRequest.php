<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\MatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Validate a match schedule edit (court / time / status). The draw engine owns
 * teams and bracket structure, so those are not editable here.
 */
class MatchUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->missing('court_id') && $this->filled('courtId')) {
            $this->merge(['court_id' => $this->input('courtId')]);
        }
        if ($this->missing('scheduled_at') && $this->filled('scheduledAt')) {
            $this->merge(['scheduled_at' => $this->input('scheduledAt')]);
        }

        // The datetime-local input carries Asia/Yangon wall-clock time; persist
        // as UTC to match how scheduled_at is stored.
        if ($this->filled('scheduled_at')) {
            $this->merge([
                'scheduled_at' => Carbon::parse(
                    (string) $this->input('scheduled_at'),
                    'Asia/Yangon',
                )->utc()->toDateTimeString(),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'court_id' => ['nullable', 'string', 'uuid', 'exists:courts,id'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(MatchStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
