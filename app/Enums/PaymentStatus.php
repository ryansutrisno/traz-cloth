<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Menunggu = 'menunggu';
    case Sukses = 'sukses';
    case Gagal = 'gagal';
    case Kedaluwarsa = 'kedaluwarsa';
    case Refund = 'refund';
}
