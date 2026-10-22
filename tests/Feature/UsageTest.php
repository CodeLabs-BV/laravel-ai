<?php

use CodeLabs\Ai\Facades\CodeLabsAi;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function keyInfo(float $spend = 2.5): array
{
    return ['key' => 'hashed', 'info' => [
        'key_alias' => 'vaybz',
        'spend' => $spend,
        'max_budget' => 10.0,
        'budget_duration' => '30d',
        'budget_reset_at' => '2026-11-01T00:00:00+00:00',
        'models' => ['qwen-agent'],
        'rpm_limit' => 60,
        'tpm_limit' => 100000,
        'max_parallel_requests' => 4,
        'status' => 'active',
    ]];
}

it('reads key usage from the server root, not the /v1 prefix', function () {
    Http::fake(['ai.code-labs.nl/key/info' => Http::response(keyInfo())]);

    $usage = CodeLabsAi::usage();

    expect($usage->alias)->toBe('vaybz')
        ->and($usage->spend)->toBe(2.5)
        ->and($usage->maxBudget)->toBe(10.0)
        ->and($usage->remaining())->toBe(7.5)
        ->and($usage->percentUsed())->toBe(25.0)
        ->and($usage->exhausted())->toBeFalse()
        ->and($usage->budgetResetAt->toDateString())->toBe('2026-11-01')
        ->and($usage->rpmLimit)->toBe(60)
        ->and($usage->models)->toBe(['qwen-agent']);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://ai.code-labs.nl/key/info'
        && $request->hasHeader('Authorization', 'Bearer sk-test'));
});

it('reports an exhausted budget', function () {
    Http::fake(['*' => Http::response(keyInfo(spend: 10.2))]);

    $usage = CodeLabsAi::usage();

    expect($usage->exhausted())->toBeTrue()
        ->and($usage->remaining())->toBe(0.0);
});

it('throws when the server rejects the key', function () {
    Http::fake(['*' => Http::response(['error' => 'invalid key'], 401)]);

    CodeLabsAi::usage();
})->throws(RequestException::class);

it('refuses a connection without a key', function () {
    config()->set('codelabs-ai.connections.codelabs.key', null);

    CodeLabsAi::usage();
})->throws(InvalidArgumentException::class);

it('prints usage from the artisan command', function () {
    Http::fake(['*' => Http::response(keyInfo())]);

    $this->artisan('codelabs-ai:usage')
        ->expectsOutputToContain('vaybz')
        ->expectsOutputToContain('2026-11-01')
        ->assertSuccessful();
});

it('pings through the AI SDK', function () {
    Http::fake(['*' => Http::response(chatCompletion('Hello friend'))]);

    $this->artisan('codelabs-ai:ping')
        ->expectsOutputToContain('Hello friend')
        ->assertSuccessful();
});
