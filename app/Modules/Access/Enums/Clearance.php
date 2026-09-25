<?php

declare(strict_types=1);

namespace App\Modules\Access\Enums;

/** Tingkat klasifikasi data yang boleh dibaca role (docs/02 §B, ADR 0007). */
enum Clearance: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Restricted = 'restricted';
    case Personal = 'personal';
    case PersonalSpecific = 'personal_specific';
}
