<?php

declare(strict_types=1);

namespace App\Domain\Category;

use App\Domain\Exception\ValidationError;

/**
 * The name of one of the five fixed categories.
 *
 * It is a value object because `loan_item.category_name` freezes it (CA-06.6) and the
 * report groups by the frozen value, not by the live catalog (ADR-004). Its width is the
 * same as `category.name`: varchar(120).
 */
final readonly class CategoryName
{
    private function __construct(public string $value)
    {
    }

    public static function of(string $raw): self
    {
        $value = trim($raw);

        if ($value === '') {
            throw ValidationError::of(
                'El nombre de la categoría es obligatorio.',
                [['field' => 'category_name', 'issue' => 'es obligatorio']]
            );
        }

        if (mb_strlen($value) > 120) {
            throw ValidationError::of(
                'El nombre de la categoría admite máximo 120 caracteres.',
                [['field' => 'category_name', 'issue' => 'admite máximo 120 caracteres']]
            );
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
