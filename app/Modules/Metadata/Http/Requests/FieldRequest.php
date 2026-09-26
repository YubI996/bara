<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Requests;

use App\Models\User;
use App\Modules\Metadata\Contracts\FieldType;
use App\Modules\Metadata\Contracts\FieldTypeRegistry;
use App\Modules\Metadata\Data\FieldInput;
use App\Modules\Metadata\Models\Entity;
use App\Shared\Data\DataClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Validasi atribut umum field. Config per tipe divalidasi oleh FieldConfigValidator di Action. */
final class FieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $this->entity());
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $types = array_map(static fn (FieldType $t): string => $t->code(), app(FieldTypeRegistry::class)->all());

        return [
            'code' => ['required', 'string', 'max:63'],
            'label' => ['required', 'string', 'min:1', 'max:120'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in($types)],
            'is_required' => ['boolean'],
            'is_unique' => ['boolean'],
            'is_indexed' => ['boolean'],
            'is_searchable' => ['boolean'],
            'classification' => ['required', Rule::enum(DataClassification::class)],
            'config' => ['nullable', 'array'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'label' => 'label',
            'help_text' => 'teks bantuan',
            'type' => 'tipe',
            'classification' => 'klasifikasi data',
        ];
    }

    public function entity(): Entity
    {
        $entity = $this->route('entity');

        return $entity instanceof Entity ? $entity : throw new NotFoundHttpException;
    }

    public function toInput(): FieldInput
    {
        $config = $this->input('config', []);
        $normalized = [];
        foreach (is_array($config) ? $config : [] as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return new FieldInput(
            code: $this->string('code')->trim()->toString(),
            label: $this->string('label')->trim()->toString(),
            helpText: $this->filled('help_text') ? $this->string('help_text')->trim()->toString() : null,
            type: $this->string('type')->toString(),
            required: $this->boolean('is_required'),
            unique: $this->boolean('is_unique'),
            indexed: $this->boolean('is_indexed'),
            searchable: $this->boolean('is_searchable'),
            classification: $this->enum('classification', DataClassification::class) ?? DataClassification::Internal,
            config: $normalized,
        );
    }
}
