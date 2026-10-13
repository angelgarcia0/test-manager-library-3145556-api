<?php

declare(strict_types=1);

namespace App\Domain\Member;

use App\Domain\Exception\ValidationError;

/**
 * T-06 · Member aggregate.
 *
 * CA-05.1: a document is mandatory and unique in the engine. CA-05.3: members are never
 * deleted, so this aggregate has no `delete()` at all — the absence of the operation IS
 * the rule, and it is what `data-model.md` section 4 calls "solo dominio (por ausencia de
 * operación)".
 */
final class Member
{
    public function __construct(
        public readonly string $id,
        public string $fullName,
        public readonly DocumentNumber $documentNumber,
    ) {
    }

    public static function create(string $id, string $fullName, DocumentNumber $documentNumber): self
    {
        return new self($id, self::requiredName($fullName), $documentNumber);
    }

    public static function rehydrate(string $id, string $fullName, DocumentNumber $documentNumber): self
    {
        return new self($id, $fullName, $documentNumber);
    }

    public function changeName(string $fullName): void
    {
        $this->fullName = self::requiredName($fullName);
    }

    private static function requiredName(string $raw): string
    {
        $value = trim($raw);

        if ($value === '') {
            throw ValidationError::of(
                'El nombre del socio es obligatorio.',
                [['field' => 'full_name', 'issue' => 'es obligatorio']]
            );
        }

        if (mb_strlen($value) > 200) {
            throw ValidationError::of(
                'El nombre del socio admite máximo 200 caracteres.',
                [['field' => 'full_name', 'issue' => 'admite máximo 200 caracteres']]
            );
        }

        return $value;
    }
}
