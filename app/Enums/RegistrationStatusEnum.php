<?php

namespace App\Enums;

enum RegistrationStatusEnum: string
{
    case PENDING              = 'pending';
    case REGISTRAR_CLEARED    = 'registrar_cleared';
    case APPROVED             = 'approved';
    case REJECTED             = 'rejected';
    case REJECTED_BY_REGISTRAR = 'rejected_by_registrar';
    case NEEDS_REVISION       = 'needs_revision';

    public function label(): string
    {
        return match ($this) {
            self::PENDING               => 'Pending Review',
            self::REGISTRAR_CLEARED     => 'Registrar Cleared',
            self::APPROVED              => 'Approved',
            self::REJECTED              => 'Rejected by Finance',
            self::REJECTED_BY_REGISTRAR => 'Rejected by Registrar',
            self::NEEDS_REVISION        => 'Needs Revision',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING               => 'yellow',
            self::REGISTRAR_CLEARED     => 'blue',
            self::APPROVED              => 'green',
            self::REJECTED              => 'red',
            self::REJECTED_BY_REGISTRAR => 'red',
            self::NEEDS_REVISION        => 'orange',
        };
    }

    /**
     * Whether this status is terminal (no further action possible).
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::REJECTED,
            self::REJECTED_BY_REGISTRAR,
        ], true);
    }

    /**
     * Whether this status is actionable by ANY staff.
     * Stage-specific checks are enforced at the controller level.
     */
    public function isActionable(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::REGISTRAR_CLEARED,
            self::NEEDS_REVISION,
        ], true);
    }

    /**
     * Whether actionable by the Registrar stage.
     * Note: NEEDS_REVISION is only Registrar-actionable when revision_stage = 'registrar'.
     * That check is enforced in the controller, not here.
     */
    public function isRegistrarActionable(): bool
    {
        return in_array($this, [self::PENDING, self::NEEDS_REVISION], true);
    }

    /**
     * Whether actionable by the Finance stage.
     * Note: NEEDS_REVISION is only Finance-actionable when revision_stage = 'finance'.
     * That check is enforced in the controller, not here.
     */
    public function isFinanceActionable(): bool
    {
        return in_array($this, [self::REGISTRAR_CLEARED, self::NEEDS_REVISION], true);
    }
}