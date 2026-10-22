<?php

use CodeLabs\Ai\CodeLabsAi;
use CodeLabs\Ai\CodeLabsProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiManager;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

use function Laravel\Ai\agent;

function chatCompletion(string $text = 'Hello there'): array
{
    return [
        'id' => 'chatcmpl-1',
        'object' => 'chat.completion',
        'model' => 'qwen-agent',
        'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $text]]],
        'usage' => ['prompt_tokens' => 19, 'completion_tokens' => 3, 'total_tokens' => 22],
    ];
}

it('registers each keyed connection as a laravel/ai provider', function () {
    expect(config('ai.providers.codelabs'))->toMatchArray([
        'driver' => 'codelabs',
        'key' => 'sk-test',
        'url' => 'https://ai.code-labs.nl/v1',
        'max_tokens' => 256,
        'models' => ['text' => ['default' => 'qwen-agent']],
    ]);

    expect(app(AiManager::class)->textProvider('codelabs'))->toBeInstanceOf(CodeLabsProvider::class);
});

it('sends chat completions to the Code-Labs endpoint with the default max_tokens', function () {
    Http::fake(['ai.code-labs.nl/v1/chat/completions' => Http::response(chatCompletion())]);

    $response = agent('Be brief.')->prompt('Hi', provider: 'codelabs');

    expect($response->text)->toBe('Hello there')
        ->and($response->usage->inputTokens)->toBe(19);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.code-labs.nl/v1/chat/completions'
        && $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'qwen-agent'
        && $request['max_tokens'] === 256);
});

it('lets an agent override max_tokens', function () {
    Http::fake(['*' => Http::response(chatCompletion())]);

    $agent = new #[MaxTokens(40)] class implements Agent
    {
        use Promptable;

        public function instructions(): string
        {
            return 'Be brief.';
        }
    };

    $agent->prompt('Hi', provider: 'codelabs');

    Http::assertSent(fn (Request $request) => $request['max_tokens'] === 40);
});

it('does not register a connection without a key', function () {
    config()->set('codelabs-ai.connections.other', ['key' => null, 'url' => 'https://x/v1', 'model' => 'm']);

    app(CodeLabsAi::class)->registerProviders();

    expect(config()->has('ai.providers.other'))->toBeFalse();
});

it('leaves a provider already defined in config/ai.php alone', function () {
    config()->set('ai.providers.own', ['driver' => 'openai-compatible', 'key' => 'mine', 'url' => 'https://own/v1']);
    config()->set('codelabs-ai.connections.own', ['key' => 'sk-other', 'url' => 'https://x/v1', 'model' => 'm']);

    app(CodeLabsAi::class)->registerProviders();

    expect(config('ai.providers.own.key'))->toBe('mine');
});
