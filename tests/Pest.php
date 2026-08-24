<?php

declare(strict_types=1);

use MaxCloudApps\LlmClient\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * A minimal successful chat-completion body, matching what an
 * OpenAI-compatible endpoint returns.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function chatCompletionBody(string $content = 'Hello there.', array $overrides = []): array
{
    return [
        'id' => 'chatcmpl-test',
        'object' => 'chat.completion',
        'model' => 'test-model',
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $content],
            'finish_reason' => 'stop',
        ]],
        'usage' => [
            'prompt_tokens' => 120,
            'completion_tokens' => 340,
            'total_tokens' => 460,
        ],
        ...$overrides,
    ];
}
