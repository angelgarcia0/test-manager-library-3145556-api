<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * The same book appears twice in one loan. Maps to 422 DUPLICATE_BOOK_IN_LOAN (CA-06.2).
 *
 * The engine also declares uq_loan_item_loan_book, so this is defended twice: once by
 * the aggregate, once by the index. CA-06.2 wants a business error, not a raw violation.
 */
final class DuplicateBookInLoan extends DomainError
{
    public static function at(int $itemIndex, string $bookId): self
    {
        return (new self('No se puede repetir el mismo libro en un préstamo.'))->withDetails([[
            'field' => sprintf('items[%d].book_id', $itemIndex),
            'issue' => 'el libro ya está en este préstamo',
        ]]);
    }
}
