<?php

namespace CodeLabs\Ai\Console;

use CodeLabs\Ai\CodeLabsAi;
use Illuminate\Console\Command;

class KeyCommand extends Command
{
    protected $signature = 'codelabs-ai:key
        {connection? : Connection name from config/codelabs-ai.php}
        {--forget : Remove the stored key, falling back to .env}';

    protected $description = 'Store the Code-Labs AI key (encrypted) so it no longer has to live in .env';

    public function handle(CodeLabsAi $ai): int
    {
        $name = $this->argument('connection') ?? $ai->defaultConnection();

        if ($this->option('forget')) {
            $ai->storeKey(null, $name);
            $this->components->info("Stored key for [{$name}] removed.");

            return self::SUCCESS;
        }

        $key = trim((string) $this->secret("Code-Labs AI key for [{$name}]"));

        if ($key === '') {
            $this->components->error('No key given.');

            return self::FAILURE;
        }

        $ai->storeKey($key, $name);
        $this->components->info("Key for [{$name}] stored ({$ai->keyHint($name)}).");

        return self::SUCCESS;
    }
}
