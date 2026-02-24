<?php

namespace App\Domain\Enums;

enum Channel: string
{
    case Email = 'email';
    case Slack = 'slack';
    case Sms = 'sms';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
