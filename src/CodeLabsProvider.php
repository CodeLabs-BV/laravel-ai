<?php

namespace CodeLabs\Ai;

use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;

/**
 * The Code-Labs LLM is an OpenAI-compatible endpoint (LiteLLM in front of
 * vLLM), so this is the SDK's openai-compatible provider with one change:
 * its gateway caps the answer length when the agent does not.
 */
class CodeLabsProvider extends OpenAiCompatibleProvider
{
    public const DRIVER = 'codelabs';

    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new CodeLabsGateway($this->events);
    }
}
