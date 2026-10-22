<?php

namespace CodeLabs\Ai;

use Carbon\CarbonImmutable;

/**
 * What a Code-Labs key has spent this budget period, from GET /key/info.
 * Amounts are in the provider's budget unit (EUR equivalent).
 */
final readonly class KeyUsage
{
    /**
     * @param  list<string>  $models
     */
    public function __construct(
        public ?string $alias,
        public float $spend,
        public ?float $maxBudget,
        public ?string $budgetDuration,
        public ?CarbonImmutable $budgetResetAt,
        public ?int $rpmLimit,
        public ?int $tpmLimit,
        public ?int $maxParallelRequests,
        public array $models,
        public ?string $status,
    ) {}

    public static function fromResponse(array $payload): self
    {
        $info = $payload['info'] ?? [];
        $int = fn (string $key): ?int => isset($info[$key]) ? (int) $info[$key] : null;

        return new self(
            alias: $info['key_alias'] ?? null,
            spend: (float) ($info['spend'] ?? 0),
            maxBudget: isset($info['max_budget']) ? (float) $info['max_budget'] : null,
            budgetDuration: $info['budget_duration'] ?? null,
            budgetResetAt: isset($info['budget_reset_at']) ? CarbonImmutable::parse($info['budget_reset_at']) : null,
            rpmLimit: $int('rpm_limit'),
            tpmLimit: $int('tpm_limit'),
            maxParallelRequests: $int('max_parallel_requests'),
            models: array_values($info['models'] ?? []),
            status: $info['status'] ?? null,
        );
    }

    /**
     * Null when the key has no budget cap.
     */
    public function remaining(): ?float
    {
        return $this->maxBudget === null ? null : max(0.0, $this->maxBudget - $this->spend);
    }

    public function percentUsed(): ?float
    {
        return $this->maxBudget ? min(100.0, $this->spend / $this->maxBudget * 100) : null;
    }

    public function exhausted(): bool
    {
        return $this->maxBudget !== null && $this->spend >= $this->maxBudget;
    }
}
