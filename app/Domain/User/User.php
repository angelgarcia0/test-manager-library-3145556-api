<?php

declare(strict_types=1);

namespace App\Domain\User;

/**
 * T-06 · User aggregate: an internal operator with a role.
 *
 * CA-02.2: the username is stored trimmed and lowercased.
 * CA-02.3: the API never returns or logs the hash, and article IX keeps it out of every
 * response. `passwordHash` is therefore never read by a resource or a log line.
 *
 * The hash is a plain string here and not a value object: hashing is the `PasswordHasher`
 * port's job, and the domain must not know which algorithm is behind it (D-09).
 */
final class User
{
    public function __construct(
        public readonly string $id,
        public readonly UserName $username,
        public readonly string $passwordHash,
        public readonly Role $role,
    ) {
    }

    public static function create(string $id, UserName $username, string $passwordHash, Role $role): self
    {
        if (trim($passwordHash) === '') {
            throw \App\Domain\Exception\ValidationError::of(
                'La contraseña es obligatoria.',
                [['field' => 'password', 'issue' => 'es obligatoria']]
            );
        }

        return new self($id, $username, $passwordHash, $role);
    }

    public static function rehydrate(string $id, UserName $username, string $passwordHash, Role $role): self
    {
        return new self($id, $username, $passwordHash, $role);
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }
}
