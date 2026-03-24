<?php

namespace Weave\OpenAI\Providers;

use OpenAI\Client;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Providers\AbstractProvider;

class OpenAIEmbeddingProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'openai.embedding';
    }

    public static function label(): string
    {
        return 'OpenAI — Embedding';
    }

    public function credentials(): array
    {
        return [
            'OPENAI_API_KEY' => ['label' => 'API Key', 'secret' => true, 'required' => true],
        ];
    }

    protected function debugFakeData(AutomationContext $context): AutomationResult
    {
        return AutomationResult::success([
            'embedding' => [],
            'tokens_used' => 0,
            'model' => $this->get('model', 'text-embedding-3-small'),
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $input = $this->resolve($context, 'input');

        if ($input === null || $input === '' || (is_array($input) && $input === [])) {
            return AutomationResult::failure('OpenAI Embedding requires a non-empty `input`.');
        }

        try {
            $response = app(Client::class)->embeddings()->create([
                'model' => (string) $this->get('model', 'text-embedding-3-small'),
                'input' => $input,
            ]);
        } catch (\Throwable $e) {
            return AutomationResult::failure("OpenAI Embedding error: {$e->getMessage()}");
        }

        return AutomationResult::success([
            'embedding' => $response->embeddings[0]->embedding,
            'tokens_used' => (int) ($response->usage->totalTokens ?? 0),
            'model' => $response->model,
        ]);
    }

    public function testConnection(): ConnectionTestResult
    {
        try {
            app(Client::class)->models()->list();

            return ConnectionTestResult::ok();
        } catch (\Throwable $e) {
            return ConnectionTestResult::failed($e->getMessage());
        }
    }
}
