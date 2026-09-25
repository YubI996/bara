<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('bara:audit-partitions --months=3')->monthlyOn(1, '01:15');
