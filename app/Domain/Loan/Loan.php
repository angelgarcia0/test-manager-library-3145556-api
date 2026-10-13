<?php

declare(strict_types=1);

namespace App\Domain\Loan;

use App\Domain\Book\Book;
use App\Domain\Exception\DuplicateBookInLoan;
use App\Domain\Exception\ItemAlreadyReturned;
use App\Domain\Exception\MemberLimitExceeded;
use App\Domain\Exception\ValidationError;

/**
 * T-05 · Loan aggregate.
 *
 * Invariants defended here, per `architecture.md` section 3:
 *   - between 1 and 3 lines
 *   - the same book never appears twice
 *   - due date is exactly 14 days after the loan
 *   - immutable except for the return of a line
 *
 * `addItem()` withdraws the copy from the Book BEFORE registering the line: discounting a
 * copy and creating the line are ONE operation (CA-06.3), and the use case wraps both in a
 * single transaction so a failure leaves nothing behind.
 *
 * There is no clock in this class. The caller passes the instant, because article I forbids
 * the domain from importing the `Clock` port that article II requires the application to
 * declare (see `consistencia.md`, INC-13).
 */
final class Loan
{
    public const PERIOD_DAYS = 14;

    public const MAX_ITEMS = 3;

    /** @var list<LoanItem> */
    private array $items = [];

    /**
     * @param list<LoanItem> $items
     */
    private function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $loanedByUserId,
        public readonly \DateTimeImmutable $loanedAt,
        array $items = [],
    ) {
        $this->items = array_values($items);
    }

    public static function start(
        string $id,
        string $memberId,
        string $loanedByUserId,
        \DateTimeImmutable $loanedAt,
    ): self {
        return new self($id, $memberId, $loanedByUserId, $loanedAt);
    }

    /**
     * Rehydration from the engine. Trust what is stored; the historical invariants were
     * already enforced when the rows were written.
     *
     * @param list<LoanItem> $items
     */
    public static function rehydrate(
        string $id,
        string $memberId,
        string $loanedByUserId,
        \DateTimeImmutable $loanedAt,
        array $items,
    ): self {
        return new self($id, $memberId, $loanedByUserId, $loanedAt, $items);
    }

    /**
     * CA-06.1 and CA-06.2. Throws before touching the book, so a rejected loan never
     * discounts a copy.
     */
    public function addItem(string $itemId, Book $book): LoanItem
    {
        if ($this->items === []) {
            $this->assertNotEmptyRequest($itemId);
        }

        if (count($this->items) >= self::MAX_ITEMS) {
            throw ValidationError::of(
                sprintf('Un préstamo admite de 1 a %d libros.', self::MAX_ITEMS),
                [['field' => 'items', 'issue' => sprintf('máximo %d libros', self::MAX_ITEMS)]]
            );
        }

        foreach ($this->items as $existing) {
            if ($existing->bookId === $book->id) {
                throw DuplicateBookInLoan::at($this->indexOfBook($book->id), $book->id);
            }
        }

        // Discount the copy first: both effects belong to the same operation.
        $book->withdraw(1);

        $item = LoanItem::freeze(
            $itemId,
            $this->id,
            $book->id,
            $book->title,
            $book->isbn,
            $book->categoryName,
            $this->loanedAt,
            self::PERIOD_DAYS,
        );

        $this->items[] = $item;

        return $item;
    }

    private function assertNotEmptyRequest(string $itemId): void
    {
        // A loan always has at least one line (CA-06.1). The guard lives here so the rule
        // travels with the aggregate instead of only in the HTTP request.
        if ($itemId === '') {
            throw ValidationError::of(
                'Un préstamo admite de 1 a 3 libros.',
                [['field' => 'items', 'issue' => 'debe traer al menos un libro']]
            );
        }
    }

    /**
     * CA-07.1: one line at a time, adds exactly one copy back. `architecture.md` section 3
     * says `returnItem` restocks through the Book, so the Book is passed in: it is the
     * aggregate that was loaded with the loan, not something the loan re-creates.
     */
    public function returnItem(string $itemId, Book $book, \DateTimeImmutable $now): LoanItem
    {
        $item = $this->itemById($itemId);

        if ($item->isReturned()) {
            // CA-07.2: never restock twice.
            throw ItemAlreadyReturned::of();
        }

        $item->markReturned($now);
        $book->restock(1);

        return $item;
    }

    public function itemById(string $itemId): LoanItem
    {
        foreach ($this->items as $item) {
            if ($item->id === $itemId) {
                return $item;
            }
        }

        throw ValidationError::of(
            'La línea no pertenece a este préstamo.',
            [['field' => 'item_id', 'issue' => 'no pertenece a este préstamo']]
        );
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<LoanItem> */
    public function unreturnedItems(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (LoanItem $item): bool => ! $item->isReturned()
        ));
    }

    /**
     * CA-06.4: at most three books without returning, counted across every loan.
     */
    public function countUnreturned(): int
    {
        return count($this->unreturnedItems());
    }

    /**
     * CA-06.5 and RN-3. `architecture.md` section 4 asks about overdue FIRST, so a member
     * with overdue lines gets 409 even when they are also at the three-book limit.
     */
    public function hasOverdue(\DateTimeImmutable $now): bool
    {
        foreach ($this->unreturnedItems() as $item) {
            if ($item->isOverdueAt($now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * CA-08.1 and CA-08.3: calculated on every read, never stored.
     */
    public function status(\DateTimeImmutable $now): LoanStatus
    {
        return LoanStatus::calculate($this->items, $now);
    }

    /**
     * CA-06.4 is a rule about the member, not about this loan. The use case sums this
     * across the member's open loans; the aggregate only knows its own lines.
     */
    public function assertMemberLimit(int $alreadyUnreturnedElsewhere): void
    {
        if ($alreadyUnreturnedElsewhere >= self::MAX_ITEMS) {
            throw MemberLimitExceeded::of($alreadyUnreturnedElsewhere, self::MAX_ITEMS);
        }
    }

    private function indexOfBook(string $bookId): int
    {
        foreach ($this->items as $index => $item) {
            if ($item->bookId === $bookId) {
                return $index;
            }
        }

        return 0;
    }
}
