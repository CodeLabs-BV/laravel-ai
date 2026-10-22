<?php

use CodeLabs\Ai\CodeLabsAi;
use CodeLabs\Ai\Concerns\UsesCodeLabs;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[Provider('groq')]
#[Model('llama-own')]
class SwitchableAgent implements Agent
{
    use Promptable, UsesCodeLabs;

    public function instructions(): string
    {
        return 'Be brief.';
    }
}

#[Provider('groq')]
#[Model('llama-own')]
class PlainAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Be brief.';
    }
}

it('runs on Code-Labs first with the own provider as failover while active', function () {
    expect(app(CodeLabsAi::class)->active())->toBeTrue()
        ->and((new SwitchableAgent)->provider())->toBe(['codelabs' => null, 'groq' => 'llama-own']);
});

it('runs only on Code-Labs when fallback is off', function () {
    config()->set('codelabs-ai.fallback', false);

    expect((new SwitchableAgent)->provider())->toBe(['codelabs' => null]);
});

it('keeps the own provider and model when Code-Labs is disabled', function () {
    config()->set('codelabs-ai.enabled', false);

    expect(app(CodeLabsAi::class)->active())->toBeFalse()
        ->and((new SwitchableAgent)->provider())->toBe(['groq' => 'llama-own']);
});

it('keeps the own provider when there is no key', function () {
    config()->set('codelabs-ai.connections.codelabs.key', null);

    expect((new SwitchableAgent)->provider())->toBe(['groq' => 'llama-own']);
});

it('leaves agents without the trait alone', function () {
    expect(app(CodeLabsAi::class)->providersFor(PlainAgent::class))->toBe(['groq' => 'llama-own']);
});

it('falls back to the SDK default provider when the agent names none', function () {
    config()->set('codelabs-ai.enabled', false);
    config()->set('ai.default', 'mistral');

    $agent = new class implements Agent
    {
        use Promptable, UsesCodeLabs;

        public function instructions(): string
        {
            return '';
        }
    };

    expect($agent->provider())->toBe(['mistral' => null]);
});

it('prompts Code-Labs with its own model, not the agent model', function () {
    Http::fake(['ai.code-labs.nl/*' => Http::response(chatCompletion())]);

    (new SwitchableAgent)->prompt('Hi');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://ai.code-labs.nl/v1')
        && $request['model'] === 'qwen-agent');
});

it('fails over to the own provider when Code-Labs is down', function () {
    config()->set('ai.providers.groq', ['driver' => 'openai-compatible', 'key' => 'g', 'url' => 'https://groq.test/v1']);

    Http::fake([
        'ai.code-labs.nl/*' => Http::response('overloaded', 503),
        'groq.test/*' => Http::response(chatCompletion('From groq')),
    ]);

    expect((new SwitchableAgent)->prompt('Hi')->text)->toBe('From groq');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://groq.test/v1')
        && $request['model'] === 'llama-own');
});
