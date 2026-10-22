<?php

namespace CodeLabs\Ai\KeyStores;

use CodeLabs\Ai\Contracts\KeyStore;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * Keys in the `codelabs_ai_keys` table, encrypted with the app key.
 *
 * Read on every boot (the providers are registered from it), so the rows are
 * cached until the next write.
 */
class DatabaseKeyStore implements KeyStore
{
    public const CACHE_KEY = 'codelabs-ai.keys';

    private const TABLE = 'codelabs_ai_keys';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Cache $cache,
        private readonly Container $container,
    ) {}

    /**
     * Resolved on use: the store is built on every boot, and an app without
     * APP_KEY must still boot when nothing is stored.
     */
    private function encrypter(): Encrypter
    {
        return $this->container->make(Encrypter::class);
    }

    public function all(): array
    {
        try {
            $rows = $this->cache->rememberForever(self::CACHE_KEY, fn () => $this->db->table(self::TABLE)
                ->get(['connection', 'key', 'enabled'])
                ->map(fn ($row) => (array) $row)
                ->all());
        } catch (Throwable) {
            // Not migrated yet, or no database at all (composer scripts,
            // config:cache in CI): behave as an empty store.
            return [];
        }

        $stored = [];

        foreach ($rows as $row) {
            $stored[$row['connection']] = [
                'key' => $this->decrypt($row['key']),
                'enabled' => $row['enabled'] === null ? null : (bool) $row['enabled'],
            ];
        }

        return $stored;
    }

    public function putKey(string $connection, ?string $key): void
    {
        $this->write($connection, ['key' => blank($key) ? null : $this->encrypter()->encryptString($key)]);
    }

    public function putEnabled(string $connection, ?bool $enabled): void
    {
        $this->write($connection, ['enabled' => $enabled]);
    }

    private function write(string $connection, array $values): void
    {
        $query = $this->db->table(self::TABLE)->where('connection', $connection);

        $query->exists()
            ? $query->update([...$values, 'updated_at' => now()])
            : $this->db->table(self::TABLE)->insert(['connection' => $connection, ...$values, 'created_at' => now(), 'updated_at' => now()]);

        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * A key encrypted under a previous APP_KEY cannot be read; treat it as
     * unset rather than failing every request.
     */
    private function decrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return $this->encrypter()->decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }
}
