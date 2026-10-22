<?php

namespace CodeLabs\Ai\Contracts;

/**
 * Where keys and on/off switches set at runtime (an admin panel, the
 * codelabs-ai:key command) live. Whatever a store holds wins over .env.
 */
interface KeyStore
{
    /**
     * Every stored connection. A null field means "not set here".
     *
     * @return array<string, array{key: ?string, enabled: ?bool}>
     */
    public function all(): array;

    public function putKey(string $connection, ?string $key): void;

    public function putEnabled(string $connection, ?bool $enabled): void;
}
