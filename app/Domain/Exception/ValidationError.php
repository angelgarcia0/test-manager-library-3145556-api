<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * A field is invalid or an input rule is broken. Maps to 422 VALIDATION_ERROR.
 *
 * Used by the value objects (ISBN shape, negative quantity, inverted date range) and by
 * any input rule the use cases enforce, such as asking for the admin role over the API.
 */
final class ValidationError extends DomainError
{
    /**
     * @param list<array{field: string, issue: string}> $details
     */
    public static function of(string $message, array $details = []): self
    {
        return (new self($message))->withDetails($details);
    }
}
