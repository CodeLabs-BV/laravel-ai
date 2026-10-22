<?php

namespace CodeLabs\Ai\Concerns;

use CodeLabs\Ai\CodeLabsAi;

/**
 * Run the agent on Code-Labs while it is active, and on the agent's own
 * #[Provider] / #[Model] otherwise (or as failover, see `codelabs-ai.fallback`).
 *
 * Use next to Laravel\Ai\Promptable, which calls provider() when it exists.
 * A provider passed to prompt() explicitly still wins over this.
 */
trait UsesCodeLabs
{
    /**
     * @return array<string, string|null>
     */
    public function provider(): array
    {
        return app(CodeLabsAi::class)->providersFor(static::class);
    }
}
