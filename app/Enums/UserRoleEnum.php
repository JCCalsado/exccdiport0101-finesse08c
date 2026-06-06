<?php

namespace App\Enums;

enum UserRoleEnum: string
{
    case ADMIN      = 'admin';
    case ACCOUNTING = 'accounting';
    case REGISTRAR  = 'registrar';
    case STUDENT    = 'student';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN      => 'Administrator',
            self::ACCOUNTING => 'Accounting Staff',
            self::REGISTRAR  => 'Registrar',
            self::STUDENT    => 'Student',
        };
    }
}