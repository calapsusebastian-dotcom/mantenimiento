<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Tecnico = 'tecnico';
    case Operador = 'operador';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Tecnico => 'Técnico',
            self::Operador => 'Operador / Inquilino',
        };
    }
}
