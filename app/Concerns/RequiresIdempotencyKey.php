<?php

namespace App\Concerns;

/**
 * Financial writes must carry a client-generated Idempotency-Key header, so that a
 * double click or a retried request can never post the same money twice.
 */
trait RequiresIdempotencyKey
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function idempotencyRules(): array
    {
        return ['idempotency_key' => ['required', 'uuid']];
    }

    public function idempotencyKey(): string
    {
        return (string) $this->validated('idempotency_key');
    }
}
