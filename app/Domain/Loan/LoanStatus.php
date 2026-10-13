<?php

declare(strict_types=1);

namespace App\Domain\Loan;

/**
 * Article VII · The state of a loan is CALCULATED, never stored.
 *
 * `data-model.md` has no `status` column, and the verification of article VII is that
 * `library:schema-report` shows none. Every `new LoanStatus(...)` is derived from the
 * lines and the current instant.
 */
enum LoanStatus: string
{
    /** There are unreturned lines and none of them is overdue. */
    case Open = 'open';

    /** Some line is unreturned and its `due_at` is in the past. */
    case Overdue = 'overdue';

    /** Every line is returned. */
    case Closed = 'closed';

    /**
     * @param list<LoanItem> $items
     */
    public static function calculate(array $items, \DateTimeImmutable $now): self
    {
        $unreturned = array_filter($items, static fn (LoanItem $item): bool => ! $item->isReturned());

        if ($unreturned === []) {
            return self::Closed;
        }

        foreach ($unreturned as $item) {
            if ($item->isOverdueAt($now)) {
                return self::Overdue;
            }
        }

        return self::Open;
    }
}
