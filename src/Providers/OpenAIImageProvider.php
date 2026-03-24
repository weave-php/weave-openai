<?php

namespace Weave\OpenAI\Providers;

use OpenAI\Client;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Providers\AbstractProvider;

class OpenAIImageProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'openai.image';
    }

    public static function label(): string
    {
        return 'OpenAI — Image Generation';
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
            'images' => [['url' => null, 'revised_prompt' => null]],
            'url' => null,
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $prompt = $this->resolve($context, 'prompt');
        if (! is_string($prompt) || trim($prompt) === '') {
            return AutomationResult::failure('OpenAI Image requires a non-empty `prompt`.');
        }

        try {
            $response = app(Client::class)->images()->create([
                'model' => (string) $this->get('model', 'dall-e-3'),
                'prompt' => $prompt,
                'n' => (int) $this->get('n', 1),
                'size' => (string) $this->get('size', '1024x1024'),
                'quality' => (string) $this->get('quality', 'standard'),
                'response_format' => (string) $this->get('response_format', 'url'),
            ]);
        } catch (\Throwable $e) {
            return AutomationResult::failure("OpenAI Image error: {$e->getMessage()}");
        }

        $images = array_map(fn ($img) => [
            'url' => $img->url,
            'revised_prompt' => $img->revisedPrompt,
        ], $response->data);

        return AutomationResult::success([
            'images' => $images,
            'url' => $images[0]['url'] ?? null,
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
