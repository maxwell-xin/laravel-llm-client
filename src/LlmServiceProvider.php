<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

final class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/llm-client.php', 'llm-client');

        $this->app->singleton(LlmClient::class, static function (Application $app): LlmClient {
            /**
             * @var array{base_url: string, api_key: ?string, model: ?string, timeout: int, connect_timeout: int} $config
             */
            $config = $app->make('config')->get('llm-client');

            return new LlmClient(
                http: $app->make(HttpFactory::class),
                baseUrl: rtrim($config['base_url'], '/'),
                apiKey: $config['api_key'] ?: null,
                model: $config['model'] ?: null,
                timeout: (int)$config['timeout'],
                connectTimeout: (int)$config['connect_timeout'],
            );
        });

        $this->app->alias(LlmClient::class, 'llm-client');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/llm-client.php' => config_path('llm-client.php'),
            ], 'llm-client-config');
        }
    }
}
