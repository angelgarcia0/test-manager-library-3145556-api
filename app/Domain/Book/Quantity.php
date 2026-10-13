<?php

declare(strict_types=1);

namespace App\Domain\Book;

use App\Domain\Exception\ValidationError;

/**
 * T-03 · Quantity value object (D-07): the total number of copies of a book.
 *
 * CA-03.3 requires total >= 1, and the domain sets the value: the engine declares no
 * DEFAULT on `total_copies`.
 *
 * `available_copies` is deliberately NOT wrapped in this class. RN-1 is a RANGE against
 * the total, not a lower bound, and a second value object would hide which invariant
 * failed. Book keeps it as an int and checks the range in `withdraw()` and `restock()`.
 */
final readonly class Quantity
{
    private function __construct(public int $value)
    {
    }

    public static function of(int $copies): self
    {
        if ($copies < 1) {
            throw ValidationError::of(
                'El total de ejemplares debe ser mayor o igual a 1.',
                [['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1']]
            );
        }

        return new self($copies);
    }

    public function isLessThan(int $other): bool
    {
        return $this->value < $other;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
