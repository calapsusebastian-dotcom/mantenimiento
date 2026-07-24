<?php

namespace App\Enums;

enum CheckoutCondition: string
{
    case Bueno = 'bueno';
    case Regular = 'regular';
    case Danado = 'dañado';

    public function label(): string
    {
        return match ($this) {
            self::Bueno => 'Bueno',
            self::Regular => 'Regular',
            self::Danado => 'Dañado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bueno => 'green',
            self::Regular => 'amber',
            self::Danado => 'red',
        };
    }
}
