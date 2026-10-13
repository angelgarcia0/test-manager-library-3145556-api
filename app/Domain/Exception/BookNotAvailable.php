<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * No copies left. Maps to 409 BOOK_NOT_AVAILABLE (CA-06.3).
 *
 * ADR-002: raised both when the aggregate refuses the withdrawal and when the
 * compare-and-swap loses the race for the last copy. A violated CHECK is a defect, not
 * this exception.
 */
final class BookNotAvailable extends DomainError
{
    public static function of(string $bookTitle, string $isbn): self
    {
        return new self(sprintf('No quedan ejemplares disponibles de «%s».', $bookTitle));
    }
}
