<?php

namespace App\Enums;

enum AccountingTypeEnum: string
{
    case CASHIER            = 'cashier';
    case BOOKKEEPER         = 'bookkeeper';
    case DISBURSING_OFFICER = 'disbursing_officer';

    public function label(): string
    {
        return match ($this) {
            self::CASHIER            => 'Cashier',
            self::BOOKKEEPER         => 'Bookkeeper',
            self::DISBURSING_OFFICER => 'Disbursing Officer',
        };
    }

    /**
     * Default permission set description for UI display.
     */
    public function description(): string
    {
        return match ($this) {
            self::CASHIER => 'Records over-the-counter payments. Read access to fee assessments.',
            self::BOOKKEEPER => 'Read-only access to financial reports and collection summaries.',
            self::DISBURSING_OFFICER => 'Full accounting access: assessments, payment approvals, fee settings, and finance registration clearance.',
        ];
    }
}