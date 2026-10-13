<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * `from` is after `to`. Maps to 422 INVALID_DATE_RANGE (CA-10.2).
 *
 * An empty range is not an error: it returns an empty list (CA-10.2, second half).
 */
final class InvalidDateRange extends DomainError
{
    public static function of(string $from, string $to): self
    {
        return (new self('El rango de fechas es inválido: la fecha final es anterior a la inicial.'))
            ->withDetails([[
                'field' => 'to',
                'issue' => sprintf('«%s» es anterior a «%s»', $to, $from),
            ]]);
    }
}
