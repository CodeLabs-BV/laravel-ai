<?php

namespace CodeLabs\Ai;

use CodeLabs\Ai\Concerns\UsesCodeLabs;
use CodeLabs\Ai\Contracts\KeyStore;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Attributes\Model as ModelAttribute;
use Laravel\Ai\Attributes\Provider as ProviderAttribute;
use Laravel\Ai\Providers\Provider;
use ReflectionClass;

class CodeLabsAi
{
    /**
     * Provider names this class put into config('ai.providers'), so a refresh
     * can update or remove them without touching ones the app defined.
     *
     * @var array<string, true>
     */
    private array $registered = [];

    /**
     * @var array<string, array{key: ?string, enabled: ?bool}>|null
     */
    private ?array $stored = null;

    public function __construct(
        private readonly Repository $config,
        private readonly Http $http,
        private readonly Container $container,
        private readonly ?KeyStore $store = null,
    ) {}

    /**
     * The connection's settings: config/codelabs-ai.php with the stored key
     * (if any) in place of the .env one.
     *
     * @return array{key: ?string, url: string, model: string, max_tokens: int}
     */
    public function connection(?string $name = null): array
    {
        $name ??= $this->defaultConnection();

        $connection = $this->config->get("codelabs-ai.connections.{$name}")
            ?? throw new InvalidArgumentException("Code-Labs AI connection [{$name}] is not defined.");

        $stored = $this->stored()[$name]['key'] ?? null;

        return filled($stored) ? [...$connection, 'key' => $stored] : $connection;
    }

    public function defaultConnection(): string
    {
        return $this->config->get('codelabs-ai.default', 'codelabs');
    }

    /**
     * Whether the connection has a key. Like any key check, this cannot tell a
     * valid key from a wrong one; `usage()` can.
     */
    public function configured(?string $name = null): bool
    {
        return filled($this->connection($name)['key'] ?? null);
    }

    /**
     * The on/off switch: a stored value wins over config('codelabs-ai.enabled').
     */
    public function enabled(?string $name = null): bool
    {
        $name ??= $this->defaultConnection();

        return $this->stored()[$name]['enabled'] ?? (bool) $this->config->get('codelabs-ai.enabled', true);
    }

    /**
     * Whether agents using the UsesCodeLabs trait should run on the connection.
     */
    public function active(?string $name = null): bool
    {
        $name ??= $this->defaultConnection();

        return $this->config->has("codelabs-ai.connections.{$name}")
            && $this->enabled($name)
            && $this->configured($name)
            && $this->config->has("ai.providers.{$name}");
    }

    /**
     * Where the key in use comes from: 'store', 'env' or null (none).
     */
    public function keySource(?string $name = null): ?string
    {
        $name ??= $this->defaultConnection();

        return match (true) {
            filled($this->stored()[$name]['key'] ?? null) => 'store',
            filled($this->config->get("codelabs-ai.connections.{$name}.key")) => 'env',
            default => null,
        };
    }

    /**
     * Safe to show: the first and last four characters of the key in use.
     */
    public function keyHint(?string $name = null): ?string
    {
        $key = $this->connection($name)['key'] ?? null;

        return filled($key) ? Str::substr($key, 0, 4).'…'.Str::substr($key, -4) : null;
    }

    /**
     * Store a key (null clears the stored one, falling back to .env) and make
     * it live in this process too.
     */
    public function storeKey(?string $key, ?string $name = null): void
    {
        $this->store()->putKey($name ?? $this->defaultConnection(), $key);
        $this->refresh();
    }

    /**
     * Store the on/off switch (null falls back to config) and apply it.
     */
    public function storeEnabled(?bool $enabled, ?string $name = null): void
    {
        $this->store()->putEnabled($name ?? $this->defaultConnection(), $enabled);
        $this->refresh();
    }

    /**
     * Expose each keyed connection as `ai.providers.<name>`, so apps need no
     * edit to config/ai.php. A provider the app defined itself is left alone.
     */
    public function registerProviders(): void
    {
        foreach (array_keys($this->config->get('codelabs-ai.connections', [])) as $name) {
            if ($this->config->has("ai.providers.{$name}") && ! isset($this->registered[$name])) {
                continue;
            }

            $connection = $this->connection($name);

            if (blank($connection['key'] ?? null)) {
                $this->forget($name);

                continue;
            }

            $this->config->set("ai.providers.{$name}", [
                'driver' => CodeLabsProvider::DRIVER,
                'key' => $connection['key'],
                'url' => $connection['url'],
                'max_tokens' => $connection['max_tokens'] ?? null,
                'models' => [
                    'text' => ['default' => $connection['model']],
                ],
            ]);

            $this->registered[$name] = true;
            $this->forgetResolvedProvider($name);
        }
    }

    /**
     * Re-read the store and re-register the providers.
     */
    public function refresh(): void
    {
        $this->stored = null;
        $this->registerProviders();
    }

    /**
     * The provider => model list an agent class runs on, in failover order.
     * A null model means the provider's default.
     *
     * Agents that do not use UsesCodeLabs, or any agent while Code-Labs is
     * inactive, get their own #[Provider] / #[Model] (or the SDK default).
     *
     * @param  class-string  $agent
     * @return array<string, string|null>
     */
    public function providersFor(string $agent): array
    {
        $reflection = new ReflectionClass($agent);
        $provider = ($reflection->getAttributes(ProviderAttribute::class)[0] ?? null)?->newInstance()->value
            ?? $this->config->get('ai.default');
        $model = ($reflection->getAttributes(ModelAttribute::class)[0] ?? null)?->newInstance()->value;

        $own = Provider::formatProviderAndModelList($provider, is_array($provider) ? null : $model);

        if (! in_array(UsesCodeLabs::class, class_uses_recursive($agent), true) || ! $this->active()) {
            return $own;
        }

        $codeLabs = [$this->defaultConnection() => null];

        return $this->config->get('codelabs-ai.fallback', true) ? $codeLabs + $own : $codeLabs;
    }

    /**
     * The key's spend, budget and limits, read live from the server.
     *
     * @throws RequestException
     */
    public function usage(?string $name = null): KeyUsage
    {
        $connection = $this->connection($name);

        if (blank($connection['key'] ?? null)) {
            throw new InvalidArgumentException('Code-Labs AI connection ['.($name ?? $this->defaultConnection()).'] has no key.');
        }

        $response = $this->http
            ->withToken($connection['key'])
            ->acceptJson()
            ->timeout($this->config->get('codelabs-ai.timeout', 10))
            ->get($this->serverUrl($connection['url']).'/key/info')
            ->throw();

        return KeyUsage::fromResponse($response->json() ?? []);
    }

    /**
     * @return array<string, array{key: ?string, enabled: ?bool}>
     */
    private function stored(): array
    {
        return $this->stored ??= $this->store?->all() ?? [];
    }

    private function store(): KeyStore
    {
        return $this->store ?? throw new InvalidArgumentException('No Code-Labs AI key store is configured (codelabs-ai.key_store).');
    }

    private function forget(string $name): void
    {
        if (isset($this->registered[$name])) {
            $this->config->set('ai.providers', array_diff_key($this->config->get('ai.providers', []), [$name => true]));
            unset($this->registered[$name]);
            $this->forgetResolvedProvider($name);
        }
    }

    /**
     * The SDK caches provider instances, which hold their key.
     */
    private function forgetResolvedProvider(string $name): void
    {
        if ($this->container->resolved(AiManager::class)) {
            $this->container->make(AiManager::class)->forgetInstance($name);
        }
    }

    /**
     * /key/info lives on the server root, not under the /v1 API prefix.
     */
    private function serverUrl(string $url): string
    {
        return preg_replace('#/v1/?$#', '', rtrim($url, '/'));
    }
}
