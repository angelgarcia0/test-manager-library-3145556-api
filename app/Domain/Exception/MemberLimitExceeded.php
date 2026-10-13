<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * The member would exceed three books without returning. Maps to 422
 * MEMBER_LIMIT_EXCEEDED (CA-06.4), and the envelope carries the current count.
 */
final class MemberLimitExceeded extends DomainError
{
    public static function of(int $currentUnreturned, int $limit = 3): self
    {
        return (new self(
            sprintf('El socio ya tiene %d libro(s) sin devolver. El máximo es %d.', $currentUnreturned, $limit)
        ))->withDetails([[
            'field' => 'member_id',
            'issue' => sprintf('tiene %d sin devolver, el máximo es %d', $currentUnreturned, $limit),
        ]]);
    }
}
