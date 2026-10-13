<?php

declare(strict_types=1);

namespace App\Domain\Member;

use App\Domain\Exception\ValidationError;

/**
 * CA-05.1 · Document number. Trimmed, non-empty, at most 30 characters, unique in the
 * engine (uq_member_document_number).
 */
final readonly class DocumentNumber
{
    private function __construct(public string $value)
    {
    }

    public static function of(string $raw): self
    {
        $value = trim($raw);

        if ($value === '') {
            throw ValidationError::of(
                'El documento del socio es obligatorio.',
                [['field' => 'document_number', 'issue' => 'es obligatorio']]
            );
        }

        if (mb_strlen($value) > 30) {
            throw ValidationError::of(
                'El documento del socio admite máximo 30 caracteres.',
                [['field' => 'document_number', 'issue' => 'admite máximo 30 caracteres']]
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
