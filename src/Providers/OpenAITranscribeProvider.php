<?php

namespace Weave\OpenAI\Providers;

use OpenAI\Client;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Providers\AbstractProvider;

class OpenAITranscribeProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'openai.transcribe';
    }

    public static function label(): string
    {
        return 'OpenAI — Audio Transcription (Whisper)';
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
            'text' => '[Weave debug — fake transcription]',
            'language' => $this->get('language', 'auto'),
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $audioPath = $this->resolve($context, 'audio_path');

        if (! is_string($audioPath) || $audioPath === '') {
            return AutomationResult::failure('OpenAI Transcribe requires a non-empty `audio_path`.');
        }

        if (! is_file($audioPath) || ! is_readable($audioPath)) {
            return AutomationResult::failure("Audio file not found or not readable: {$audioPath}");
        }

        $params = [
            'model' => (string) $this->get('model', 'whisper-1'),
            'file' => fopen($audioPath, 'rb'),
            'response_format' => (string) $this->get('response_format', 'text'),
        ];

        $language = $this->get('language');
        if (is_string($language) && $language !== '' && $language !== 'auto') {
            $params['language'] = $language;
        }

        try {
            $response = app(Client::class)->audio()->transcribe($params);
        } catch (\Throwable $e) {
            if (isset($params['file']) && is_resource($params['file'])) {
                fclose($params['file']);
            }

            return AutomationResult::failure("OpenAI Transcribe error: {$e->getMessage()}");
        }

        if (isset($params['file']) && is_resource($params['file'])) {
            fclose($params['file']);
        }

        return AutomationResult::success([
            'text' => $response->text,
            'language' => is_string($language) && $language !== '' ? $language : 'auto',
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
