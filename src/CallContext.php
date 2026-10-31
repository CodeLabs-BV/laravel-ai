<?php

namespace CodeLabs\Ai;

use Closure;

/**
 * What the gateway should know about the calls made in the current scope:
 * which feature and tenant they belong to and which conversation they are a
 * part of. It becomes `x-litellm-*` request headers, so the gateway's spend
 * logs can be split by tag, customer and session instead of only by key.
 *
 * @phpstan-type Values array{tags?: list<string>, session?: ?string, customer?: ?string, metadata?: array<string, mixed>, cache?: ?int}
 */
class CallContext
{
    /** @var list<string> */
    private array $tags = [];

    private ?string $session = null;

    private ?string $customer = null;

    /** @var array<string, mixed> */
    private array $metadata = [];

    private ?int $cacheTtl = null;

    /**
     * @param  list<string>  $defaultTags  always sent, e.g. the app name
     */
    public function __construct(private readonly array $defaultTags = []) {}

    /**
     * Run `$callback` with extra context; the previous context is restored
     * afterwards, also when it throws.
     *
     * Keys: `tags` (list), `session` (conversation id), `customer` (tenant or
     * user id), `metadata` (extra spend-log fields), `cache` (seconds, opts
     * the chat calls in the scope into the gateway's response cache).
     *
     * @param  Values  $values
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function with(array $values, Closure $callback): mixed
    {
        $previous = [$this->tags, $this->session, $this->customer, $this->metadata, $this->cacheTtl];

        $this->tags = array_values(array_unique([...$this->tags, ...($values['tags'] ?? [])]));
        $this->session = $values['session'] ?? $this->session;
        $this->customer = $values['customer'] ?? $this->customer;
        $this->metadata = [...$this->metadata, ...($values['metadata'] ?? [])];
        $this->cacheTtl = $values['cache'] ?? $this->cacheTtl;

        try {
            return $callback();
        } finally {
            [$this->tags, $this->session, $this->customer, $this->metadata, $this->cacheTtl] = $previous;
        }
    }

    /**
     * Add a tag for the rest of the request, without a callback.
     */
    public function tag(string ...$tags): void
    {
        $this->tags = array_values(array_unique([...$this->tags, ...$tags]));
    }

    public function cacheTtl(): ?int
    {
        return $this->cacheTtl;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $tags = array_values(array_unique([...$this->defaultTags, ...$this->tags]));

        return array_filter([
            'x-litellm-tags' => $tags === [] ? null : implode(',', $tags),
            'x-litellm-session-id' => $this->session,
            'x-litellm-customer-id' => $this->customer,
            'x-litellm-spend-logs-metadata' => $this->metadata === [] ? null : json_encode($this->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], fn (?string $value) => filled($value));
    }
}
