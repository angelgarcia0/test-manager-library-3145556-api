<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use App\Domain\Book\Isbn;
use App\Domain\Category\CategoryName;

/**
 * One book inside a loan.
 *
 * CA-06.6 and ADR-004: `bookTitle`, `isbn` and `categoryName` are FROZEN copies taken at
 * the instant of the loan. They never follow the catalog, which is why renaming a book in
 * November does not change a report closed in October.
 *
 * `categoryName` has no foreign key on purpose.
 *
 * The only thing that ever changes is `returned_at` (RN-5: loans and returns are not
 * deleted; a line is not edited).
 */
final class LoanItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $loanId,
        public readonly string $bookId,
        public readonly string $bookTitle,
        public readonly Isbn $isbn,
        public readonly CategoryName $categoryName,
        public readonly \DateTimeImmutable $dueAt,
        private ?\DateTimeImmutable $returnedAt = null,
    ) {
    }

    public static function freeze(
        string $id,
        string $loanId,
        string $bookId,
        string $bookTitle,
        Isbn $isbn,
        CategoryName $categoryName,
        \DateTimeImmutable $loanedAt,
        int $periodDays = Loan::PERIOD_DAYS,
    ): self {
        return new self(
            $id,
            $loanId,
            $bookId,
            $bookTitle,
            $isbn,
            $categoryName,
            $loanedAt->modify(sprintf('+%d days', $periodDays)),
        );
    }

    public function returnedAt(): ?\DateTimeImmutable
    {
        return $this->returnedAt;
    }

    public function isReturned(): bool
    {
        return $this->returnedAt !== null;
    }

    public function markReturned(\DateTimeImmutable $now): void
    {
        $this->returnedAt ??= $now;
    }

    public function isOverdueAt(\DateTimeImmutable $now): bool
    {
        return ! $this->isReturned() && $this->dueAt < $now;
    }

    /**
     * Article VII: calculated, never stored, and `data-model.md` lists no `days_late`
     * column. Whole elapsed days in UTC (see `consistencia.md`, INC-15).
     */
    public function daysLateAt(\DateTimeImmutable $now): int
    {
        if (! $this->isOverdueAt($now)) {
            return 0;
        }

        return (int) floor(($now->getTimestamp() - $this->dueAt->getTimestamp()) / 86400);
    }
}
