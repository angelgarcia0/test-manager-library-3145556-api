<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * The member already holds overdue lines, so RN-3 blocks a new loan.
 * Maps to 409 MEMBER_HAS_OVERDUE (CA-06.5).
 *
 * This check runs BEFORE the three-book limit: `architecture.md` section 4 asks about
 * overdue first, and 409 is a state conflict while 422 is input validation.
 */
final class MemberHasOverdue extends DomainError
{
    public static function count(int $overdueLines): self
    {
        return (new self(
            sprintf('El socio tiene %d libro(s) vencido(s). Debe devolverlos antes de pedir otro.', $overdueLines)
        ))->withDetails([[
            'field' => 'member_id',
            'issue' => sprintf('%d libro(s) vencido(s)', $overdueLines),
        ]]);
    }
}
