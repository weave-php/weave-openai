<?php

namespace Weave\OpenAI\Providers;

use OpenAI\Client;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Providers\AbstractProvider;

class OpenAIAgentProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'openai.agent';
    }

    public static function label(): string
    {
        return 'OpenAI — Agent (tools)';
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
            'content' => $this->get('fake_content', '[Weave debug — agent, no OpenAI call]'),
            'iterations' => 1,
            'model' => $this->get('model', 'gpt-4o'),
            'tool_rounds' => 0,
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $messages = $this->buildInitialMessages($context);

        if ($messages === []) {
            return AutomationResult::failure('OpenAI Agent requires `prompt`, `messages`, or resolvable templates.');
        }

        $client = app(Client::class);
        $model = (string) $this->get('model', 'gpt-4o');
        $maxIterations = max(1, (int) $this->get('max_iterations', 15));

        $toolsConfig = $this->get('tools', []);
        if (! is_array($toolsConfig)) {
            return AutomationResult::failure('OpenAI Agent `tools` must be an array.');
        }

        [$apiTools, $handlers] = $this->parseTools($toolsConfig);

        if ($apiTools === []) {
            return $this->singleCompletion($client, $context, $messages, $model);
        }

        $totalTokens = 0;
        $toolRounds = 0;

        for ($i = 0; $i < $maxIterations; $i++) {
            $params = [
                'model' => $model,
                'messages' => $messages,
                'tools' => $apiTools,
                'tool_choice' => $this->get('tool_choice', 'auto'),
                'max_tokens' => (int) $this->get('max_tokens', 4096),
                'temperature' => (float) $this->get('temperature', 0.7),
            ];

            try {
                $response = $client->chat()->create($params);
            } catch (\Throwable $e) {
                return AutomationResult::failure("OpenAI Agent error: {$e->getMessage()}");
            }

            $totalTokens += (int) ($response->usage->totalTokens ?? 0);

            $assistantDto = $response->choices[0]->message;
            $assistantMessage = $assistantDto->toArray();

            $toolCalls = $assistantMessage['tool_calls'] ?? [];

            if ($toolCalls === []) {
                $content = $assistantMessage['content'] ?? '';

                return AutomationResult::success([
                    'content' => is_string($content) ? $content : '',
                    'iterations' => $i + 1,
                    'tool_rounds' => $toolRounds,
                    'tokens_used' => $totalTokens,
                    'model' => $response->model,
                    'finish_reason' => $response->choices[0]->finishReason,
                ]);
            }

            $messages[] = $assistantMessage;
            $toolRounds++;

            foreach ($toolCalls as $tc) {
                $toolCallId = $tc['id'] ?? '';
                $fn = $tc['function']['name'] ?? '';
                $argsJson = $tc['function']['arguments'] ?? '{}';
                $args = is_string($argsJson) ? json_decode($argsJson, true) : [];
                if (! is_array($args)) {
                    $args = [];
                }

                $handler = $handlers[$fn] ?? null;
                $output = $this->runToolHandler($handler, $context, $fn, $args);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCallId,
                    'content' => $output,
                ];
            }
        }

        return AutomationResult::failure('OpenAI Agent stopped: max_iterations reached without a final assistant message.');
    }

    private function parseTools(array $toolsConfig): array
    {
        $apiTools = [];
        $handlers = [];

        foreach ($toolsConfig as $tool) {
            if (! is_array($tool)) {
                continue;
            }

            $name = $tool['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }

            $parameters = $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass];

            $apiTools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => (string) ($tool['description'] ?? ''),
                    'parameters' => $parameters,
                ],
            ];

            if (isset($tool['execute']) && is_callable($tool['execute'])) {
                $handlers[$name] = $tool['execute'];
            }
        }

        return [$apiTools, $handlers];
    }

    private function runToolHandler(mixed $handler, AutomationContext $context, string $fn, array $args): string
    {
        if ($handler === null) {
            return json_encode(['error' => "No execute handler for tool [{$fn}]"], JSON_THROW_ON_ERROR);
        }

        try {
            $result = $handler($context, $args);
        } catch (\Throwable $e) {
            return json_encode([
                'error' => 'Tool execution failed',
                'message' => $e->getMessage(),
            ], JSON_THROW_ON_ERROR);
        }

        if (is_string($result)) {
            return $result;
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    private function singleCompletion(Client $client, AutomationContext $context, array $messages, string $model): AutomationResult
    {
        try {
            $response = $client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => (int) $this->get('max_tokens', 4096),
                'temperature' => (float) $this->get('temperature', 0.7),
            ]);
        } catch (\Throwable $e) {
            return AutomationResult::failure("OpenAI Agent error: {$e->getMessage()}");
        }

        $content = $response->choices[0]->message->content ?? '';

        return AutomationResult::success([
            'content' => is_string($content) ? $content : '',
            'iterations' => 1,
            'tool_rounds' => 0,
            'tokens_used' => (int) ($response->usage->totalTokens ?? 0),
            'model' => $response->model,
            'finish_reason' => $response->choices[0]->finishReason,
        ]);
    }

    private function buildInitialMessages(AutomationContext $context): array
    {
        $messages = $this->resolve($context, 'messages');

        if (is_string($messages)) {
            $messages = [['role' => 'user', 'content' => $messages]];
        }

        if (! is_array($messages) || $messages === []) {
            $prompt = $this->resolve($context, 'prompt');
            if (! is_string($prompt) || $prompt === '') {
                return [];
            }
            $messages = [['role' => 'user', 'content' => $prompt]];
        }

        $system = $this->resolve($context, 'system');
        if (is_string($system) && $system !== '') {
            array_unshift($messages, ['role' => 'system', 'content' => $system]);
        }

        return $messages;
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
