<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Exception\ValidationError;

/**
 * CA-02.2 · Username, stored trimmed and lowercased. The domain normalises, the engine
 * only enforces uniqueness (uq_user_username).
 *
 * The contract example sends `"Ana "` and expects `ana` back, so normalisation is not
 * optional and cannot be deferred to the database.
 */
final readonly class UserName
{
    private function __construct(public string $value)
    {
    }

    public static function of(string $raw): self
    {
        $value = mb_strtolower(trim($raw));

        if ($value === '') {
            throw ValidationError::of(
                'El nombre de usuario es obligatorio.',
                [['field' => 'username', 'issue' => 'es obligatorio']]
            );
        }

        if (mb_strlen($value) > 120) {
            throw ValidationError::of(
                'El nombre de usuario admite máximo 120 caracteres.',
                [['field' => 'username', 'issue' => 'admite máximo 120 caracteres']]
            );
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
