<?php

namespace CodeLabs\Ai;

use Psr\Http\Message\ResponseInterface;

/**
 * What the gateway reported about one finished call, read from its
 * `x-litellm-*` response headers. Null where the gateway sent nothing.
 */
final readonly class CallInfo
{
    public function __construct(
        public ?string $callId,
        public ?string $model,
        public ?float $cost,
        public ?float $keySpend,
        public ?int $retries,
        public ?int $fallbacks,
        public ?float $durationMs,
        public ?float $overheadMs,
        public ?string $version,
        public int $status,
    ) {}

    public static function fromResponse(ResponseInterface $response): self
    {
        $header = fn (string $name): ?string => ($value = $response->getHeaderLine($name)) === '' ? null : $value;
        $number = fn (string $name): ?float => is_numeric($value = $header($name)) ? (float) $value : null;
        $count = fn (string $name): ?int => is_numeric($value = $header($name)) ? (int) $value : null;

        return new self(
            callId: $header('x-litellm-call-id'),
            model: $header('x-litellm-model-group'),
            cost: $number('x-litellm-response-cost'),
            keySpend: $number('x-litellm-key-spend'),
            retries: $count('x-litellm-attempted-retries'),
            fallbacks: $count('x-litellm-attempted-fallbacks'),
            durationMs: $number('x-litellm-response-duration-ms'),
            overheadMs: $number('x-litellm-overhead-duration-ms'),
            version: $header('x-litellm-version'),
            status: $response->getStatusCode(),
        );
    }
}
