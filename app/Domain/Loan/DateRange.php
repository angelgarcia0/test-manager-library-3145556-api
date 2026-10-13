<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use App\Domain\Exception\InvalidDateRange;

/**
 * T-03 · DateRange value object (D-07): the window of a report or a loan listing.
 *
 * CA-10.2: a final date before the initial one is invalid; an empty range is NOT an
 * error, it returns an empty list. Both boundaries are inclusive dates in UTC
 * (see `consistencia.md`, INC-09), and the repository turns the upper bound into a
 * half-open interval so the last second of the day is never lost.
 */
final readonly class DateRange
{
    private function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
    ) {
    }

    public static function between(\DateTimeImmutable $from, \DateTimeImmutable $to): self
    {
        if ($to < $from) {
            throw InvalidDateRange::of(
                $from->format('Y-m-d'),
                $to->format('Y-m-d')
            );
        }

        return new self($from, $to);
    }

    /** Exclusive upper bound: midnight of the day AFTER `to`. */
    public function toExclusive(): \DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }

    public function contains(\DateTimeImmutable $instant): bool
    {
        return $instant >= $this->from && $instant < $this->toExclusive();
    }
}
