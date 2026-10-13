<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * The book cannot be retired while it has open loans. Maps to 409
 * BOOK_HAS_OPEN_LOANS (CA-03.5).
 */
final class BookHasOpenLoans extends DomainError
{
    public static function count(int $openLines): self
    {
        return (new self(
            sprintf('El libro tiene %d préstamo(s) abierto(s) y no se puede dar de baja.', $openLines)
        ))->withDetails([[
            'field' => 'book_id',
            'issue' => sprintf('%d préstamo(s) abierto(s)', $openLines),
        ]]);
    }
}
