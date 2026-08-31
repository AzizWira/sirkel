<?php

namespace App\Enums;

enum PartnerCapability: string
{
    case COLLECTION = 'collection';
    case PICKUP = 'pickup';
    case REPAIR = 'repair';
    case REUSE_DONATION = 'reuse_donation';
    case RECOVERY = 'recovery';
    case SPECIAL_HANDLING = 'special_handling';

    public function label(): string
    {
        return match ($this) {
            self::COLLECTION => 'Pengumpulan / Antar Langsung',
            self::PICKUP => 'Penjemputan',
            self::REPAIR => 'Perbaikan',
            self::REUSE_DONATION => 'Guna Ulang / Donasi',
            self::RECOVERY => 'Pemulihan Material',
            self::SPECIAL_HANDLING => 'Penanganan Khusus',
        };
    }
}
