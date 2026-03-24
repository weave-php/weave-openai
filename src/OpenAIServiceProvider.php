<?php

namespace Weave\OpenAI;

use Illuminate\Support\ServiceProvider;
use OpenAI;
use OpenAI\Client;
use Weave\Core\AutomationRegistry;
use Weave\Credentials\CredentialManager;
use Weave\OpenAI\Providers\OpenAIAgentProvider;
use Weave\OpenAI\Providers\OpenAIChatProvider;
use Weave\OpenAI\Providers\OpenAIEmbeddingProvider;
use Weave\OpenAI\Providers\OpenAIImageProvider;
use Weave\OpenAI\Providers\OpenAITranscribeProvider;

class OpenAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function () {
            $apiKey = app(CredentialManager::class)->get('OPENAI_API_KEY')
                ?? config('services.openai.api_key')
                ?? env('OPENAI_API_KEY');

            return OpenAI::client($apiKey);
        });
    }

    public function boot(): void
    {
        static::registerAutomationProviders();
    }

    public static function registerAutomationProviders(): void
    {
        AutomationRegistry::registerProvider(OpenAIAgentProvider::class);
        AutomationRegistry::registerProvider(OpenAIChatProvider::class);
        AutomationRegistry::registerProvider(OpenAIEmbeddingProvider::class);
        AutomationRegistry::registerProvider(OpenAIImageProvider::class);
        AutomationRegistry::registerProvider(OpenAITranscribeProvider::class);
    }
}
