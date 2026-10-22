<?php

use CodeLabs\Ai\CodeLabsAi;
use CodeLabs\Ai\Contracts\KeyStore;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AiManager;

use function Laravel\Ai\agent;

it('uses a stored key over the .env one, everywhere', function () {
    $ai = app(CodeLabsAi::class);

    $ai->storeKey('sk-from-database-1234');

    expect($ai->keySource())->toBe('store')
        ->and($ai->keyHint())->toBe('sk-f…1234')
        ->and(config('ai.providers.codelabs.key'))->toBe('sk-from-database-1234');

    Http::fake(['*' => Http::response(chatCompletion())]);
    agent('Be brief.')->prompt('Hi', provider: 'codelabs');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-from-database-1234'));
});

it('keeps the key encrypted at rest', function () {
    app(CodeLabsAi::class)->storeKey('sk-secret-value');

    $raw = DB::table('codelabs_ai_keys')->value('key');

    expect($raw)->not->toContain('sk-secret-value')
        ->and(decrypt($raw, unserialize: false))->toBe('sk-secret-value');
});

it('registers the provider once a key is stored, with no .env key at all', function () {
    config()->set('codelabs-ai.connections.codelabs.key', null);
    config()->set('ai.providers', array_diff_key(config('ai.providers'), ['codelabs' => true]));

    $ai = new CodeLabsAi(config(), app(Factory::class), app(), app(KeyStore::class));
    $ai->registerProviders();

    expect(config()->has('ai.providers.codelabs'))->toBeFalse()
        ->and($ai->active())->toBeFalse();

    $ai->storeKey('sk-new');

    expect(config('ai.providers.codelabs.key'))->toBe('sk-new')
        ->and($ai->active())->toBeTrue();

    $ai->storeKey(null);

    expect(config()->has('ai.providers.codelabs'))->toBeFalse()
        ->and($ai->keySource())->toBeNull();
});

it('falls back to the .env key when the stored one is removed', function () {
    $ai = app(CodeLabsAi::class);

    $ai->storeKey('sk-db');
    $ai->storeKey(null);

    expect($ai->keySource())->toBe('env')
        ->and(config('ai.providers.codelabs.key'))->toBe('sk-test');
});

it('drops the cached SDK provider so a new key takes effect immediately', function () {
    $ai = app(CodeLabsAi::class);
    $before = app(AiManager::class)->textProvider('codelabs');

    $ai->storeKey('sk-rotated');

    expect(app(AiManager::class)->textProvider('codelabs'))->not->toBe($before)
        ->and(app(AiManager::class)->textProvider('codelabs')->providerCredentials()['key'])->toBe('sk-rotated');
});

it('switches Code-Labs off and on from the store', function () {
    $ai = app(CodeLabsAi::class);

    $ai->storeEnabled(false);
    expect($ai->enabled())->toBeFalse()->and($ai->active())->toBeFalse();

    $ai->storeEnabled(null);
    expect($ai->enabled())->toBeTrue()->and($ai->active())->toBeTrue();
});

it('reads the store across instances (it survives the request)', function () {
    app(CodeLabsAi::class)->storeKey('sk-persisted');

    $fresh = new CodeLabsAi(config(), app(Factory::class), app(), app(KeyStore::class));

    expect($fresh->connection()['key'])->toBe('sk-persisted');
});

it('stores a key from the command', function () {
    $this->artisan('codelabs-ai:key')
        ->expectsQuestion('Code-Labs AI key for [codelabs]', 'sk-typed-in-9999')
        ->expectsOutputToContain('sk-t…9999')
        ->assertSuccessful();

    expect(app(CodeLabsAi::class)->connection()['key'])->toBe('sk-typed-in-9999');

    $this->artisan('codelabs-ai:key --forget')->assertSuccessful();

    expect(app(CodeLabsAi::class)->keySource())->toBe('env');
});

it('treats a key it cannot decrypt as unset', function () {
    DB::table('codelabs_ai_keys')->insert(['connection' => 'codelabs', 'key' => 'not-encrypted', 'created_at' => now(), 'updated_at' => now()]);
    app(CodeLabsAi::class)->refresh();

    expect(app(CodeLabsAi::class)->keySource())->toBe('env');
});
