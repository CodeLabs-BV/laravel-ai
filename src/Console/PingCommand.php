<?php

namespace CodeLabs\Ai\Console;

use CodeLabs\Ai\CodeLabsAi;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Ai\agent;

class PingCommand extends Command
{
    protected $signature = 'codelabs-ai:ping {connection? : Connection name from config/codelabs-ai.php}';

    protected $description = 'Send one tiny prompt through the Laravel AI SDK to check the Code-Labs connection';

    public function handle(CodeLabsAi $ai): int
    {
        $name = $this->argument('connection') ?? $ai->defaultConnection();

        if (! $ai->configured($name)) {
            $this->components->error("Code-Labs AI connection [{$name}] has no key.");

            return self::FAILURE;
        }

        try {
            $response = agent('Answer in at most three words.')->prompt('Say hello.', provider: $name);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Reply', trim($response->text));
        $this->components->twoColumnDetail('Tokens', "{$response->usage->inputTokens} in, {$response->usage->outputTokens} out");

        return self::SUCCESS;
    }
}
