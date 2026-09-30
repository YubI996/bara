<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Requests;

use App\Modules\Metadata\Models\Application;
use Illuminate\Foundation\Http\FormRequest;

final class ConsumerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof Application && ($this->user()?->can('update', $application) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'entity_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['entity_id' => 'entity yang dipakai', 'reason' => 'alasan pemakaian'];
    }
}
