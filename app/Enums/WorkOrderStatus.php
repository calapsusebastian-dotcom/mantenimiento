<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Pendiente = 'pendiente';
    case Asignada = 'asignada';
    case EnProgreso = 'en_progreso';
    case Completada = 'completada';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Asignada => 'Asignada',
            self::EnProgreso => 'En progreso',
            self::Completada => 'Completada',
            self::Cancelada => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'slate',
            self::Asignada => 'blue',
            self::EnProgreso => 'amber',
            self::Completada => 'green',
            self::Cancelada => 'red',
        };
    }
}
