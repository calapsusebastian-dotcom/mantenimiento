<?php

namespace App\Enums;

enum WorkOrderPriority: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Urgente => 'Urgente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Baja => 'slate',
            self::Media => 'blue',
            self::Alta => 'amber',
            self::Urgente => 'red',
        };
    }
}
