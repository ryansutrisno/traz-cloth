<?php

namespace App\Enums;

enum PaymentType: string
{
    case Dp = 'dp';
    case Full = 'full';
    case Pelunasan = 'pelunasan';
}
