# codelabs/laravel-ai

Code-Labs' self-hosted LLM (`https://ai.code-labs.nl/v1`, Qwen3-14B as `qwen-agent`) as a provider for the [Laravel AI SDK](https://github.com/laravel/ai), plus key usage and budget reporting.

Requires PHP 8.5+, Laravel 12/13 and `laravel/ai` ^1.2.

## Install

```bash
composer require codelabs/laravel-ai
```

```bash
php artisan migrate                # creates codelabs_ai_keys
php artisan codelabs-ai:key        # prompts for the key, stores it encrypted
```

Or keep it in `.env` (a stored key wins over it):

```dotenv
CODELABS_AI_KEY=sk-...            # one key per domain, issued by Code-Labs
# CODELABS_AI_ENABLED=true        # master switch (a stored switch wins)
# CODELABS_AI_FALLBACK=true       # keep each agent's own provider as failover
# CODELABS_AI_URL=https://ai.code-labs.nl/v1
# CODELABS_AI_MODEL=qwen-agent
# CODELABS_AI_MAX_TOKENS=512
```

That's all. You don't need to edit `config/ai.php`. The package registers a `codelabs` provider (driver `codelabs`, built on the SDK's `openai-compatible` driver, so it uses chat completions). A provider with the same name that you define in `config/ai.php` takes precedence.

Optionally publish the config: `php artisan vendor:publish --tag=codelabs-ai-config`.

## Use it in an agent

To switch existing agents over whenever Code-Labs is active, add the `UsesCodeLabs` trait and leave their own provider in place:

```php
use CodeLabs\Ai\Concerns\UsesCodeLabs;

#[Provider(Lab::OpenRouter)]
#[Model('deepseek/deepseek-v4-flash')]
#[MaxTokens(800)]
class MatchAdvisorAgent implements Agent { use Promptable, UsesCodeLabs; /* ... */ }
```

When the connection is **active** (it has a key and is enabled), the agent runs on Code-Labs and fails over to OpenRouter if Code-Labs is unreachable, overloaded or rate limited. Turn the failover off with `CODELABS_AI_FALLBACK=false`. When the connection is not active, the agent runs exactly as it would without the package. `CodeLabsAi::providersFor(MatchAdvisorAgent::class)` returns the list the agent will try, which is useful for "is this feature available" checks.

To always use Code-Labs, set the provider directly: `#[Provider('codelabs')]`, or ad hoc `agent('Be brief.')->prompt('Hi', provider: 'codelabs')`.

## Key storage and admin panels

Keys and the on/off switch can be set at runtime. The default `DatabaseKeyStore` keeps them in `codelabs_ai_keys`, with the key encrypted using the app key. Stored values win over `.env`, and a change takes effect immediately in the same request.

```php
CodeLabsAi::storeKey('sk-...');   // null removes it (falls back to .env)
CodeLabsAi::storeEnabled(false);  // null hands the switch back to config
CodeLabsAi::keySource();          // 'store' | 'env' | null
CodeLabsAi::keyHint();            // 'sk-a…wxyz', safe to show
CodeLabsAi::active();
```

An admin panel only needs those calls. Never send the key back to the browser; `keyHint()` is what to show. To keep keys somewhere else, set `key_store` in the config to your own `CodeLabs\Ai\Contracts\KeyStore` implementation, or to `null` to use `.env` only.

The model's context window is **8192 tokens for prompt and answer together**, so it suits short messages and summaries only. Every request without its own `#[MaxTokens]` gets the connection's `max_tokens` (default 512). Without that cap the server would let the answer run to the end of the window.

## Usage and budget

```bash
php artisan codelabs-ai:usage   # spend, budget, reset date, rate limits
php artisan codelabs-ai:ping    # one tiny prompt through the SDK
php artisan codelabs-ai:key     # store a key (--forget removes it)
```

```php
use CodeLabs\Ai\Facades\CodeLabsAi;

$usage = CodeLabsAi::usage();   // GET /key/info, live
$usage->spend; $usage->maxBudget; $usage->remaining(); $usage->percentUsed();
$usage->exhausted(); $usage->budgetResetAt; $usage->rpmLimit; $usage->tpmLimit;
```

Limits per key: 60 requests/min, 100,000 tokens/min, 4 parallel requests and a budget per 30 days. Going over a limit gets a 429 from the server, which the SDK raises as `Laravel\Ai\Exceptions\RateLimitedException`.

## Several domains in one app

Add one connection per key in `config/codelabs-ai.php`. Each one becomes an SDK provider of the same name:

```php
'connections' => [
    'codelabs' => [/* default key */],
    'codelabs-opub' => [
        'key' => env('CODELABS_AI_OPUB_KEY'),
        'url' => 'https://ai.code-labs.nl/v1',
        'model' => 'qwen-agent',
        'max_tokens' => 512,
    ],
],
```

`#[Provider('codelabs-opub')]`, `php artisan codelabs-ai:usage codelabs-opub`, `CodeLabsAi::usage('codelabs-opub')`.

A connection without a key is not registered, so a feature that checks `config("ai.providers.<name>.key")` reports it as unavailable.

## Tests

```bash
composer install && vendor/bin/pest
```
