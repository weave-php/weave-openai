<?php

use Weave\Core\AutomationRegistry;
use Weave\OpenAI\Providers\OpenAIAgentProvider;
use Weave\OpenAI\Providers\OpenAIChatProvider;
use Weave\OpenAI\Providers\OpenAIEmbeddingProvider;
use Weave\OpenAI\Providers\OpenAIImageProvider;
use Weave\OpenAI\Providers\OpenAITranscribeProvider;

it('registers all OpenAI automation providers', function (): void {
    expect(AutomationRegistry::provider('openai.agent'))->toBe(OpenAIAgentProvider::class)
        ->and(AutomationRegistry::provider('openai.chat'))->toBe(OpenAIChatProvider::class)
        ->and(AutomationRegistry::provider('openai.embedding'))->toBe(OpenAIEmbeddingProvider::class)
        ->and(AutomationRegistry::provider('openai.image'))->toBe(OpenAIImageProvider::class)
        ->and(AutomationRegistry::provider('openai.transcribe'))->toBe(OpenAITranscribeProvider::class);
});
