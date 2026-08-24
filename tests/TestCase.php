<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Tests;

use MaxCloudApps\LlmClient\LlmServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [LlmServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('llm-client.base_url', 'http://llm.test/v1');
        $app['config']->set('llm-client.api_key', 'test-key');
        $app['config']->set('llm-client.model', 'test-model');
    }
}
