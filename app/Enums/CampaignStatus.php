<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case SoldOut = 'sold_out';
    case Disabled = 'disabled';
}
