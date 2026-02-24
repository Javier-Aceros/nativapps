<?php

namespace App\Domain\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
}
