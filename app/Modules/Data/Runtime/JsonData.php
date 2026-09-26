<?php

declare(strict_types=1);

namespace App\Modules\Data\Runtime;

final class JsonData
{
    /** @return array<string, mixed> */
    public static function decode(mixed $json): array
    {
        $data = is_string($json) ? json_decode($json, true) : null;
        $out = [];

        foreach (is_array($data) ? $data : [] as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
