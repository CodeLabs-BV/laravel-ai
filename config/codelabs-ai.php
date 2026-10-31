<?php

use CodeLabs\Ai\KeyStores\DatabaseKeyStore;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Connection
    |--------------------------------------------------------------------------
    |
    | The connection the usage/ping commands and the CodeLabsAi facade use when
    | no name is given. Every connection below is also registered as a Laravel
    | AI SDK provider of the same name, so agents can target it with
    | #[Provider('codelabs')].
    |
    */

    'default' => env('CODELABS_AI_CONNECTION', 'codelabs'),

    /*
    |--------------------------------------------------------------------------
    | Activation
    |--------------------------------------------------------------------------
    |
    | Agents using the UsesCodeLabs trait run on the default connection while
    | it is active: enabled here AND given a key. Otherwise they use their own
    | #[Provider] / #[Model] attributes, exactly as without this package.
    |
    | With `fallback` on, an active agent keeps its own provider as a failover
    | for when Code-Labs is unreachable, overloaded or rate limited.
    |
    */

    'enabled' => (bool) env('CODELABS_AI_ENABLED', true),

    'fallback' => (bool) env('CODELABS_AI_FALLBACK', true),

    /*
    |--------------------------------------------------------------------------
    | Key Store
    |--------------------------------------------------------------------------
    |
    | Keys and on/off switches set at runtime (admin panel, `codelabs-ai:key`)
    | are kept here and win over .env. The database store keeps them encrypted
    | in the `codelabs_ai_keys` table (migration ships with the package).
    | Any class implementing CodeLabs\Ai\Contracts\KeyStore works; null turns
    | runtime keys off so only .env is used.
    |
    */

    'key_store' => DatabaseKeyStore::class,

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | One entry per Code-Labs API key. Code-Labs issues a key per domain
    | (vaybz.com, ...), so an app serving several domains adds one connection
    | per key. A connection is only registered when it has a key, and never
    | overrides a provider of the same name already defined in config/ai.php.
    |
    | `max_tokens` is sent on every request that does not set its own
    | (#[MaxTokens] on the agent). The model's whole context window is
    | 8192 tokens, prompt and answer together, so keep this low.
    |
    */

    'connections' => [
        'codelabs' => [
            'key' => env('CODELABS_AI_KEY'),
            'url' => env('CODELABS_AI_URL', 'https://ai.code-labs.nl/v1'),
            'model' => env('CODELABS_AI_MODEL', 'qwen-agent'),
            'max_tokens' => (int) env('CODELABS_AI_MAX_TOKENS', 512),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    |
    | Sent on every call as `x-litellm-tags`, so the gateway's spend can be
    | split per app. Add per-feature tags in code with
    | CodeLabsAi::withContext(['tags' => ['feature:summaries']], fn () => ...).
    |
    */

    'tags' => array_filter([env('CODELABS_AI_TAG', env('APP_NAME') ? \Illuminate\Support\Str::slug(env('APP_NAME')) : null)]),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait on the key usage endpoint (/key/info). Text generation
    | timeouts are set per agent with #[Timeout], as for any other provider.
    |
    */

    'timeout' => (int) env('CODELABS_AI_TIMEOUT', 10),

];
