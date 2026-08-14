<?php

namespace App\Enums;

enum WebhookProvider: string
{
    case Midtrans = 'midtrans';
    case Biteship = 'biteship';
}
