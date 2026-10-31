<?php

namespace CodeLabs\Ai\Facades;

use CodeLabs\Ai\CallContext;
use CodeLabs\Ai\CallInfo;
use CodeLabs\Ai\KeyUsage;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array connection(?string $name = null)
 * @method static string defaultConnection()
 * @method static bool configured(?string $name = null)
 * @method static bool enabled(?string $name = null)
 * @method static bool active(?string $name = null)
 * @method static string|null keySource(?string $name = null)
 * @method static string|null keyHint(?string $name = null)
 * @method static void storeKey(?string $key, ?string $name = null)
 * @method static void storeEnabled(?bool $enabled, ?string $name = null)
 * @method static void refresh()
 * @method static array providersFor(string $agent)
 * @method static KeyUsage usage(?string $name = null)
 * @method static CallContext context()
 * @method static mixed withContext(array $values, \Closure $callback)
 * @method static CallInfo|null lastCall()
 *
 * @see \CodeLabs\Ai\CodeLabsAi
 */
class CodeLabsAi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \CodeLabs\Ai\CodeLabsAi::class;
    }
}
