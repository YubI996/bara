<?php

declare(strict_types=1);

namespace App\Modules\Metadata\Schema;

final readonly class FieldChange
{
    /**
     * @param  'added'|'removed'|'modified'  $kind
     * @param  list<string>  $messages
     */
    public function __construct(
        public string $fieldKey,
        public string $code,
        public string $label,
        public string $kind,
        public ChangeCategory $category,
        public array $messages,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field_key' => $this->fieldKey,
            'code' => $this->code,
            'label' => $this->label,
            'kind' => $this->kind,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'messages' => $this->messages,
        ];
    }
}
