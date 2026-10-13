<?php

declare(strict_types=1);

namespace App\Domain\Book;

use App\Domain\Exception\ValidationError;

/**
 * CA-03.4 · Opaque storage key. NEVER a path, never bytes, never an empty string.
 *
 * The contract returns `cover_key: null` when there is no cover, so an empty string is
 * not a legal value: absence is null and a string is a real key.
 */
final readonly class CoverKey
{
    private function __construct(public string $value)
    {
    }

    public static function of(string $raw): self
    {
        $value = trim($raw);

        if ($value === '') {
            throw ValidationError::of(
                'La clave de portada no puede estar vacía.',
                [['field' => 'cover_key', 'issue' => 'no puede estar vacía']]
            );
        }

        if (mb_strlen($value) > 512) {
            throw ValidationError::of(
                'La clave de portada admite máximo 512 caracteres.',
                [['field' => 'cover_key', 'issue' => 'admite máximo 512 caracteres']]
            );
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
