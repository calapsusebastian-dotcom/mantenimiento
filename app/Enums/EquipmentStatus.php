<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case Operativo = 'operativo';
    case EnMantenimiento = 'en_mantenimiento';
    case FueraDeServicio = 'fuera_servicio';

    public function label(): string
    {
        return match ($this) {
            self::Operativo => 'Operativo',
            self::EnMantenimiento => 'En mantenimiento',
            self::FueraDeServicio => 'Fuera de servicio',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Operativo => 'green',
            self::EnMantenimiento => 'amber',
            self::FueraDeServicio => 'red',
        };
    }
}
