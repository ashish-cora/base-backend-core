<?php

namespace App\Core\Authentication\Domain\Enums;

/**
 * Account status per DB Design §14 + PRD §15.1 (account state validation).
 * Stored as string; allowed values guarded by cast + PgSQL check constraint.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}
