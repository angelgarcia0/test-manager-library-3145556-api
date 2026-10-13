<?php

declare(strict_types=1);

namespace App\Domain\Book;

use App\Domain\Category\CategoryName;
use App\Domain\Exception\BookNotAvailable;
use App\Domain\Exception\ValidationError;

/**
 * T-04 · Book aggregate.
 *
 * Invariants defended here, per `architecture.md` section 3:
 *   - available is always between 0 and total (RN-1)
 *   - total >= 1 (CA-03.3)
 *   - title is never empty
 *
 * It knows nothing about persistence. The value expected by the compare-and-swap lives
 * in the repository, not in this object (ADR-005), so a Book loaded and re-saved by the
 * adapter cannot pretend to know about versions.
 *
 * `categoryName` travels with the aggregate because CA-06.6 freezes it on the loan line.
 * It is a read of the catalog, not a second source of truth.
 *
 * Only `id` and `isbn` are readonly: the ISBN is immutable over the API, because `PUT
 * /books/{id}` replaces title, author, category and total and nothing else.
 */
final class Book
{
    private function __construct(
        public readonly string $id,
        public string $title,
        public string $author,
        public readonly Isbn $isbn,
        public string $categoryId,
        public CategoryName $categoryName,
        public Quantity $total,
        private int $available,
        public ?CoverKey $cover,
        public ?\DateTimeImmutable $deletedAt,
    ) {
    }

    /**
     * CA-03.1: the available copies start equal to the total.
     */
    public static function create(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        CategoryName $categoryName,
        Quantity $total,
    ): self {
        $trimmedTitle = trim($title);

        if ($trimmedTitle === '') {
            throw ValidationError::of(
                'El título es obligatorio.',
                [['field' => 'title', 'issue' => 'es obligatorio']]
            );
        }

        $trimmedAuthor = trim($author);

        if ($trimmedAuthor === '') {
            throw ValidationError::of(
                'El autor es obligatorio.',
                [['field' => 'author', 'issue' => 'es obligatorio']]
            );
        }

        return new self(
            $id,
            $trimmedTitle,
            $trimmedAuthor,
            $isbn,
            $categoryId,
            $categoryName,
            $total,
            $total->value,
            null,
            null,
        );
    }

    /**
     * Rehydration from a stored row. It does NOT run the create rules: the aggregate
     * trusts what the engine already accepted, so a historical row can always be read.
     */
    public static function rehydrate(
        string $id,
        string $title,
        string $author,
        Isbn $isbn,
        string $categoryId,
        CategoryName $categoryName,
        Quantity $total,
        int $available,
        ?CoverKey $cover,
        ?\DateTimeImmutable $deletedAt,
    ): self {
        return new self($id, $title, $author, $isbn, $categoryId, $categoryName, $total, $available, $cover, $deletedAt);
    }

    public function availableCopies(): int
    {
        return $this->available;
    }

    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    /**
     * Withdraw copies. Never below zero: that is half of RN-1, and the other half is
     * ck_book_available_range in the engine.
     */
    public function withdraw(int $copies = 1): void
    {
        if ($copies < 1 || $this->available - $copies < 0) {
            throw BookNotAvailable::of($this->title, $this->isbn->value);
        }

        $this->available -= $copies;
    }

    public function canWithdraw(int $copies = 1): bool
    {
        return $copies >= 1 && $this->available - $copies >= 0;
    }

    /**
     * Restock copies. Never above the total: CA-07.3 says the engine guarantees it, and
     * ck_book_available_range is that guarantee. A violation here would be a defect.
     */
    public function restock(int $copies = 1): void
    {
        if ($copies < 1 || $this->available + $copies > $this->total->value) {
            throw ValidationError::of(
                'La reposición dejaría los ejemplares disponibles por encima del total.',
                [['field' => 'available_copies', 'issue' => 'no puede superar el total']]
            );
        }

        $this->available += $copies;
    }

    /**
     * ADR-003 · Logical retirement. The row stays, the catalog stops showing it, and the
     * isbn becomes available again because uq_book_isbn_active is partial.
     *
     * Whether the book has open loans is NOT decided here: that needs loan history, so it
     * belongs to SoftDeleteBookService.
     */
    public function softDelete(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }

    /**
     * CA-03.3: changing the total shifts the available by the same difference and never
     * leaves available below zero. `newTotal >= currentlyLent` is the equivalent form,
     * and it is the one reported back to the user.
     */
    public function retotal(Quantity $total): void
    {
        $lent = $this->total->value - $this->available;

        if ($total->value < $lent) {
            throw ValidationError::of(
                sprintf('No se puede reducir el total de ejemplares: hay %d libro(s) prestado(s).', $lent),
                [[
                    'field' => 'total_copies',
                    'issue' => 'el total no puede ser menor que los ejemplares prestados',
                ]]
            );
        }

        $this->total = $total;
        $this->available = $total->value - $lent;
    }

    public function changeTitle(string $title): void
    {
        $this->title = self::requiredText($title, 'title', 'El título es obligatorio.');
    }

    public function changeAuthor(string $author): void
    {
        $this->author = self::requiredText($author, 'author', 'El autor es obligatorio.');
    }

    public function changeCategory(string $categoryId, CategoryName $categoryName): void
    {
        $this->categoryId = $categoryId;
        $this->categoryName = $categoryName;
    }

    public function withCover(CoverKey $key): void
    {
        $this->cover = $key;
    }

    /**
     * data-model.md section 7: null the key FIRST and let the transaction commit; the
     * binary is deleted afterwards. That order is mandatory and lives in the use case.
     */
    public function removeCover(): void
    {
        $this->cover = null;
    }

    private static function requiredText(string $raw, string $field, string $message): string
    {
        $value = trim($raw);

        if ($value === '') {
            throw ValidationError::of($message, [['field' => $field, 'issue' => 'es obligatorio']]);
        }

        return $value;
    }
}
