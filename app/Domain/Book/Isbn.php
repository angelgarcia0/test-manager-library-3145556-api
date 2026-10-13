<?php

declare(strict_types=1);

namespace App\Domain\Book;

use App\Domain\Exception\ValidationError;

/**
 * T-03 · ISBN value object (D-07).
 *
 * CA-03.2: thirteen digits. The column is `char(13)` in the engine, so the domain stores
 * exactly thirteen characters and never trims a padded value into a different number.
 */
final readonly class Isbn
{
    private function __construct(public string $value)
    {
    }

    public static function of(string $raw): self
    {
        $value = trim($raw);

        if (! ctype_digit($value) || strlen($value) !== 13) {
            throw ValidationError::of(
                'El ISBN debe tener exactamente 13 dígitos.',
                [['field' => 'isbn', 'issue' => 'debe tener exactamente 13 dígitos']]
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
