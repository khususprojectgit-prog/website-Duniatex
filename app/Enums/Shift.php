<?php

namespace App\Enums;

enum Shift: string
{
    case PAGI  = 'pagi';
    case SIANG = 'siang';
    case MALAM = 'malam';

    public function label(): string
    {
        return match ($this) {
            self::PAGI  => 'Shift Pagi',
            self::SIANG => 'Shift Siang',
            self::MALAM => 'Shift Malam',
        };
    }
}
