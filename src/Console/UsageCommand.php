<?php

namespace CodeLabs\Ai\Console;

use CodeLabs\Ai\CodeLabsAi;
use Illuminate\Console\Command;
use Throwable;

class UsageCommand extends Command
{
    protected $signature = 'codelabs-ai:usage {connection? : Connection name from config/codelabs-ai.php}';

    protected $description = "Show the Code-Labs AI key's spend, budget and limits";

    public function handle(CodeLabsAi $ai): int
    {
        try {
            $usage = $ai->usage($this->argument('connection'));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $money = fn (?float $value): string => $value === null ? 'unlimited' : number_format($value, 4);

        $this->components->twoColumnDetail('Key', $usage->alias ?? '-');
        $this->components->twoColumnDetail('Status', $usage->status ?? '-');
        $this->components->twoColumnDetail('Models', implode(', ', $usage->models) ?: '-');
        $this->components->twoColumnDetail('Spend', $money($usage->spend));
        $this->components->twoColumnDetail('Budget', $money($usage->maxBudget).($usage->budgetDuration ? " / {$usage->budgetDuration}" : ''));
        $this->components->twoColumnDetail('Remaining', $money($usage->remaining()));
        $this->components->twoColumnDetail('Used', $usage->percentUsed() === null ? '-' : number_format($usage->percentUsed(), 2).'%');
        $this->components->twoColumnDetail('Budget resets', $usage->budgetResetAt?->toDateTimeString() ?? '-');
        $this->components->twoColumnDetail('Limits', sprintf(
            '%s req/min, %s tokens/min, %s parallel',
            $usage->rpmLimit ?? '-',
            $usage->tpmLimit ?? '-',
            $usage->maxParallelRequests ?? '-',
        ));

        if ($usage->exhausted()) {
            $this->components->warn('The budget is used up; requests will be refused until it resets.');
        }

        return self::SUCCESS;
    }
}
