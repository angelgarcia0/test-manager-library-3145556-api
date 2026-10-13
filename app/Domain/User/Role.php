<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Exception\ValidationError;

/**
 * The only two roles. Article: the admin is provisioned by the deployment from the
 * environment and NOBODY grants it at runtime, so both values are legal in the domain.
 * What the API may accept is a use-case rule, not a domain one — see CreateLibrarianService.
 *
 * The engine declares `ck_user_role` in T-18; until then this class is the only guard.
 */
enum Role: string
{
    case Admin = 'admin';
    case Librarian = 'librarian';

    public static function fromString(string $raw): self
    {
        $role = self::tryFrom($raw);

        if ($role === null) {
            throw ValidationError::of(
                'El rol debe ser «admin» o «librarian».',
                [['field' => 'role', 'issue' => 'debe ser «admin» o «librarian»']]
            );
        }

        return $role;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function canOperateLibrary(): bool
    {
        return $this === self::Admin || $this === self::Librarian;
    }
}
