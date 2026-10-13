<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * The line was already returned. Maps to 409 ITEM_ALREADY_RETURNED (CA-07.2).
 *
 * The compare-and-swap on `returned_at IS NULL` is what prevents the double restock, so
 * this is a business outcome rather than a race that has to be retried.
 */
final class ItemAlreadyReturned extends DomainError
{
    public static function of(): self
    {
        return new self('Esta línea del préstamo ya fue devuelta.');
    }
}
