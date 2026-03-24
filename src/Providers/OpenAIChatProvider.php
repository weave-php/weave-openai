<?php

namespace Weave\OpenAI\Providers;

use OpenAI\Client;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Providers\AbstractProvider;

class OpenAIChatProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'openai.chat';
    }

    public static function label(): string
    {
        return 'OpenAI — Chat Completion';
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
            'content' => $this->get('fake_content', '[Weave debug — chat, no OpenAI call]'),
            'tokens_used' => 0,
            'model' => $this->get('model', 'gpt-4o-mini'),
            'finish_reason' => 'stop',
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $client = app(Client::class);
        $messages = $this->resolve($context, 'messages');

        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }

        if (! is_array($messages)) {
            $prompt = $this->resolve($context, 'prompt');
            if (! is_string($prompt) || $prompt === '') {
                return AutomationResult::failure('OpenAI Chat requires `messages` or a non-empty `prompt`.');
            }
            $messages = [['role' => 'user', 'content' => $prompt]];
        }

        if ($messages === []) {
            return AutomationResult::failure('OpenAI Chat requires at least one message.');
        }

        $system = $this->resolve($context, 'system');
        if (is_string($system) && $system !== '') {
            array_unshift($messages, ['role' => 'system', 'content' => $system]);
        }

        try {
            $response = $client->chat()->create([
                'model' => (string) $this->get('model', 'gpt-4o-mini'),
                'messages' => $messages,
                'max_tokens' => (int) $this->get('max_tokens', 1000),
                'temperature' => (float) $this->get('temperature', 0.7),
            ]);
        } catch (\Throwable $e) {
            return AutomationResult::failure("OpenAI error: {$e->getMessage()}");
        }

        $content = $response->choices[0]->message->content ?? '';

        return AutomationResult::success([
            'content' => is_string($content) ? $content : '',
            'tokens_used' => (int) ($response->usage->totalTokens ?? 0),
            'model' => $response->model,
            'finish_reason' => $response->choices[0]->finishReason,
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
