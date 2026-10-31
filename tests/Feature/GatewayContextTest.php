<?php

use CodeLabs\Ai\Events\CodeLabsCallCompleted;
use CodeLabs\Ai\Facades\CodeLabsAi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

use function Laravel\Ai\agent;

function completion(): array
{
    return [
        'id' => 'chatcmpl-1',
        'object' => 'chat.completion',
        'model' => 'qwen-agent',
        'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => 'Hi']]],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1, 'total_tokens' => 6],
    ];
}

it('sends the context as gateway headers and restores it afterwards', function () {
    config(['codelabs-ai.tags' => ['pitchbase']]);
    app()->forgetInstance(\CodeLabs\Ai\CodeLabsAi::class);
    CodeLabsAi::refresh();
    Http::fake(['ai.code-labs.nl/*' => Http::response(completion())]);

    CodeLabsAi::withContext(['tags' => ['feature:summary'], 'session' => 'conv-1', 'customer' => 'club-7', 'metadata' => ['team' => 3]], function () {
        agent('Be brief.')->prompt('Hi', provider: 'codelabs');
    });
    agent('Be brief.')->prompt('Again', provider: 'codelabs');

    $requests = Http::recorded()->map(fn ($pair) => $pair[0])->values();

    expect($requests[0])->toBeInstanceOf(Request::class)
        ->and($requests[0]->header('x-litellm-tags'))->toBe(['pitchbase,feature:summary'])
        ->and($requests[0]->header('x-litellm-session-id'))->toBe(['conv-1'])
        ->and($requests[0]->header('x-litellm-customer-id'))->toBe(['club-7'])
        ->and(json_decode($requests[0]->header('x-litellm-spend-logs-metadata')[0], true))->toBe(['team' => 3])
        ->and($requests[1]->header('x-litellm-tags'))->toBe(['pitchbase'])
        ->and($requests[1]->header('x-litellm-session-id'))->toBe([]);
});

it('opts a call into the gateway cache only inside a cache context', function () {
    Http::fake(['ai.code-labs.nl/*' => Http::response(completion())]);

    CodeLabsAi::withContext(['cache' => 600], fn () => agent('Be brief.')->prompt('Hi', provider: 'codelabs'));
    agent('Be brief.')->prompt('Hi', provider: 'codelabs');

    $bodies = Http::recorded()->map(fn ($pair) => $pair[0]->data())->values();

    expect($bodies[0]['cache'])->toBe(['ttl' => 600])
        ->and($bodies[1])->not->toHaveKey('cache');
});

it('reports what the gateway says about each call', function () {
    Event::fake([CodeLabsCallCompleted::class]);
    Http::fake(['ai.code-labs.nl/*' => Http::response(completion(), 200, [
        'x-litellm-call-id' => 'call-9',
        'x-litellm-response-cost' => '0.0012',
        'x-litellm-key-spend' => '1.5',
        'x-litellm-attempted-retries' => '1',
        'x-litellm-attempted-fallbacks' => '0',
        'x-litellm-response-duration-ms' => '812.5',
        'x-litellm-version' => '1.106.6',
    ])]);

    agent('Be brief.')->prompt('Hi', provider: 'codelabs');

    $call = CodeLabsAi::lastCall();

    expect($call->callId)->toBe('call-9')
        ->and($call->cost)->toBe(0.0012)
        ->and($call->keySpend)->toBe(1.5)
        ->and($call->retries)->toBe(1)
        ->and($call->fallbacks)->toBe(0)
        ->and($call->durationMs)->toBe(812.5);

    Event::assertDispatched(CodeLabsCallCompleted::class, fn ($e) => $e->connection === 'codelabs' && $e->call->callId === 'call-9');
});

it('leaves unreported fields null', function () {
    Http::fake(['ai.code-labs.nl/*' => Http::response(completion())]);

    agent('Be brief.')->prompt('Hi', provider: 'codelabs');

    expect(CodeLabsAi::lastCall()->cost)->toBeNull()->and(CodeLabsAi::lastCall()->status)->toBe(200);
});

it('falls back to the original cost header, which is all the gateway sends for local models', function () {
    Http::fake(['ai.code-labs.nl/*' => Http::response(completion(), 200, ['x-litellm-response-cost-original' => '0.0'])]);

    agent('Be brief.')->prompt('Hi', provider: 'codelabs');

    expect(CodeLabsAi::lastCall()->cost)->toBe(0.0);
});
