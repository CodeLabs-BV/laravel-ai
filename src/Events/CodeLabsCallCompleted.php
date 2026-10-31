<?php

namespace CodeLabs\Ai\Events;

use CodeLabs\Ai\CallInfo;

/**
 * Dispatched after every call to the gateway (chat, embeddings), with what the
 * gateway reported: cost, call id, retries and fallbacks. Listen to it to log
 * cost per feature or to alert when a call needed a fallback.
 */
final class CodeLabsCallCompleted
{
    public function __construct(
        public readonly string $connection,
        public readonly string $url,
        public readonly CallInfo $call,
    ) {}
}
