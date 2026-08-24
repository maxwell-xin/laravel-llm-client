<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Facades;

use Illuminate\Support\Facades\Facade;
use MaxCloudApps\LlmClient\Data\ChatResponse;
use MaxCloudApps\LlmClient\Data\Model;
use MaxCloudApps\LlmClient\LlmClient;

/**
 * @method static ChatResponse chat(array $messages, ?string $model = null, array $options = [])
 * @method static ChatResponse prompt(string $prompt, ?string $system = null, ?string $model = null, array $options = [])
 * @method static list<Model> models()
 *
 * @see LlmClient
 */
final class Llm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LlmClient::class;
    }
}
