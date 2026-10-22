<?php

namespace CodeLabs\Ai;

use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;

class CodeLabsGateway extends OpenAiCompatibleGateway
{
    /**
     * Without max_tokens the server lets the answer run to the end of the
     * 8192-token context window, which is slow and burns the monthly budget.
     * The connection's `max_tokens` is the fallback; #[MaxTokens] still wins.
     */
    protected function buildStepBody(
        Provider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        StepContext $stepContext,
    ): array {
        $body = parent::buildStepBody($provider, $model, $instructions, $messages, $tools, $schema, $options, $stepContext);

        $default = $provider->additionalConfiguration()['max_tokens'] ?? null;

        if (! isset($body['max_tokens']) && $default > 0) {
            $body['max_tokens'] = (int) $default;
        }

        return $body;
    }
}
