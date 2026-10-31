<?php

namespace CodeLabs\Ai;

use CodeLabs\Ai\Events\CodeLabsCallCompleted;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use Psr\Http\Message\ResponseInterface;

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

        // Opt-in response cache: only calls made inside withContext(['cache' => ttl]).
        if (($ttl = app(CodeLabsAi::class)->context()->cacheTtl()) !== null && ! isset($body['cache'])) {
            $body['cache'] = ['ttl' => $ttl];
        }

        return $body;
    }

    /**
     * Every request carries the current context as `x-litellm-*` headers, and
     * every response is reported back as a CodeLabsCallCompleted event.
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        $codeLabs = app(CodeLabsAi::class);

        return parent::client($provider, $timeout)
            ->withHeaders($codeLabs->context()->headers())
            ->withResponseMiddleware(function (ResponseInterface $response) use ($codeLabs, $provider): ResponseInterface {
                $codeLabs->recordCall($provider->name(), CallInfo::fromResponse($response));

                return $response;
            });
    }
}
