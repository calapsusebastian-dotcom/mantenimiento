<?php

namespace App\Enums;

enum WorkOrderType: string
{
    case Preventivo = 'preventivo';
    case Correctivo = 'correctivo';

    public function label(): string
    {
        return match ($this) {
            self::Preventivo => 'Preventivo',
            self::Correctivo => 'Correctivo',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Preventivo => 'blue',
            self::Correctivo => 'amber',
        };
    }
}
