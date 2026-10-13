<?php

declare(strict_types=1);

namespace App\Domain\Exception;

/**
 * Article I: the domain imports nothing. `RuntimeException` is SPL, so it is allowed.
 *
 * The domain deliberately knows nothing about HTTP. It does not carry status codes or
 * error codes; `architecture.md` section 5 says only the HTTP adapter translates these
 * into the single error envelope of the contract.
 */
abstract class DomainError extends \RuntimeException
{
    /** @var list<array{field: string, issue: string}> */
    private array $details = [];

    /**
     * @param list<array{field: string, issue: string}> $details
     */
    public function withDetails(array $details): static
    {
        $this->details = $details;

        return $this;
    }

    /**
     * Article XI: messages to the user are in Spanish. The adapter sends this verbatim.
     *
     * @return list<array{field: string, issue: string}>
     */
    public function details(): array
    {
        return $this->details;
    }
}
