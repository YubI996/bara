<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Http\Requests;

use App\Models\User;
use App\Modules\Metadata\Data\EntityData;
use App\Modules\Metadata\Models\Application;
use App\Modules\Metadata\Models\Entity;
use App\Shared\Validation\Identifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $entity = $this->entity();

        return $user instanceof User && ($entity !== null
            ? $user->can('update', $entity)
            : $user->can('update', $this->application()));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'name_plural' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_visibility' => ['required', Rule::in(['private', 'internal', 'partner', 'public'])],
            'is_shared' => ['boolean'],
            'title_template' => ['nullable', 'string', 'max:200'],
        ];

        if ($this->entity() === null) {
            $rules['code'] = [
                'required', 'string', new Identifier,
                Rule::unique('entities', 'code')->where('application_id', $this->application()->id),
            ];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'name_plural' => 'nama jamak',
            'description' => 'deskripsi',
            'default_visibility' => 'visibilitas bawaan',
            'is_shared' => 'dibagikan',
            'title_template' => 'template judul',
        ];
    }

    public function application(): Application
    {
        $entity = $this->entity();
        if ($entity !== null) {
            return $entity->application;
        }

        $application = $this->route('application');

        return $application instanceof Application ? $application : throw new NotFoundHttpException;
    }

    public function entity(): ?Entity
    {
        $entity = $this->route('entity');

        return $entity instanceof Entity ? $entity : null;
    }

    public function toData(): EntityData
    {
        return new EntityData(
            name: $this->string('name')->trim()->toString(),
            namePlural: $this->string('name_plural')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            defaultVisibility: $this->string('default_visibility')->toString(),
            isShared: $this->boolean('is_shared'),
            titleTemplate: $this->string('title_template')->trim()->toString(),
            code: $this->entity() === null ? $this->string('code')->toString() : null,
        );
    }
}
