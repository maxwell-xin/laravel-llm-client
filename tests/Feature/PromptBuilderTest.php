<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MaxCloudApps\LlmClient\Data\Message;
use MaxCloudApps\LlmClient\LlmClient;
use MaxCloudApps\LlmClient\Prompts\BuildsMessages;
use MaxCloudApps\LlmClient\Prompts\PromptBuilder;

/**
 * The smallest builder a consumer could write: fixed rules in the system half,
 * the caller's value in the user half.
 */
final class GreetingPromptBuilder implements PromptBuilder
{
    use BuildsMessages;

    public function __construct(private readonly string $name) {}

    public function systemInstruction(): string
    {
        return 'You are helpful.';
    }

    public function userPrompt(): string
    {
        return "Say hello to {$this->name}.";
    }
}

it('builds a system turn followed by a user turn', function () {
    $messages = (new GreetingPromptBuilder('Ada'))->messages();

    expect($messages)->toHaveCount(2)
        ->and($messages[0])->toBeInstanceOf(Message::class)
        ->and($messages[0]->role)->toBe('system')
        ->and($messages[0]->content)->toBe('You are helpful.')
        ->and($messages[1]->role)->toBe('user')
        ->and($messages[1]->content)->toBe('Say hello to Ada.');
});

it('sends a built prompt straight through the client', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    app(LlmClient::class)->chat((new GreetingPromptBuilder('Ada'))->messages());

    Http::assertSent(function (Request $request): bool {
        expect($request['messages'])->toBe([
            ['role' => 'system', 'content' => 'You are helpful.'],
            ['role' => 'user', 'content' => 'Say hello to Ada.'],
        ]);

        return true;
    });
});
