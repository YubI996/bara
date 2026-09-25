<?php

declare(strict_types=1);

namespace App\Modules\Data\Contracts;

enum Visibility: string
{
    case Private = 'private';
    case Internal = 'internal';
    case Partner = 'partner';
    case Public = 'public';
}
