<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MenungguPembayaran = 'menunggu_pembayaran';
    case DpDibayar = 'dp_dibayar';
    case Produksi = 'produksi';
    case SiapKirim = 'siap_kirim';
    case Lunas = 'lunas';
    case Dikirim = 'dikirim';
    case Delivered = 'delivered';
    case DibatalkanBuyer = 'dibatalkan_buyer';
    case DibatalkanSistem = 'dibatalkan_sistem';
}
