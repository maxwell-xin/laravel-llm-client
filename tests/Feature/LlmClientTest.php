<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use MaxCloudApps\LlmClient\Data\Message;
use MaxCloudApps\LlmClient\Exceptions\LlmConnectionException;
use MaxCloudApps\LlmClient\Exceptions\LlmRateLimitException;
use MaxCloudApps\LlmClient\Exceptions\LlmRequestException;
use MaxCloudApps\LlmClient\Exceptions\LlmResponseException;
use MaxCloudApps\LlmClient\Facades\Llm;
use MaxCloudApps\LlmClient\LlmClient;

// ── Requests ─────────────────────────────────────────────────────────────────

it('posts a chat completion to the configured endpoint', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    app(LlmClient::class)->chat([
        Message::system('You are helpful.'),
        Message::user('Hi.'),
    ]);

    Http::assertSent(function (Request $request): bool {
        expect($request->url())->toBe('http://llm.test/v1/chat/completions')
            ->and($request->method())->toBe('POST')
            ->and($request->header('Authorization'))->toBe(['Bearer test-key'])
            ->and($request['model'])->toBe('test-model')
            ->and($request['messages'])->toBe([
                ['role' => 'system', 'content' => 'You are helpful.'],
                ['role' => 'user', 'content' => 'Hi.'],
            ]);

        return true;
    });
});

it('prefers the model given to the call over the configured default', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    app(LlmClient::class)->chat([Message::user('Hi.')], model: 'other-model');

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'other-model');
});

it('passes extra options through to the endpoint', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    app(LlmClient::class)->chat(
        [Message::user('Hi.')],
        options: ['temperature' => 0.4, 'max_tokens' => 100],
    );

    Http::assertSent(fn (Request $request): bool => $request['temperature'] === 0.4 && $request['max_tokens'] === 100);
});

it('never lets options overwrite the model or messages', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    app(LlmClient::class)->chat(
        [Message::user('Hi.')],
        options: ['model' => 'smuggled', 'messages' => []],
    );

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'test-model' && $request['messages'] !== []);
});

it('builds a system and user turn from prompt()', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    Llm::prompt('Plan a trip.', system: 'You are a travel agent.');

    Http::assertSent(fn (Request $request): bool => $request['messages'] === [
        ['role' => 'system', 'content' => 'You are a travel agent.'],
        ['role' => 'user', 'content' => 'Plan a trip.'],
    ]);
});

it('sends only a user turn when prompt() gets no system text', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    Llm::prompt('Plan a trip.');

    Http::assertSent(fn (Request $request): bool => $request['messages'] === [
        ['role' => 'user', 'content' => 'Plan a trip.'],
    ]);
});

it('omits the authorization header when no api key is configured', function () {
    config(['llm-client.api_key' => null]);
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    app(LlmClient::class)->chat([Message::user('Hi.')]);

    Http::assertSent(fn (Request $request): bool => $request->header('Authorization') === []);
});

it('fails when no model is given and none is configured', function () {
    config(['llm-client.model' => null]);
    Http::fake();

    expect(fn () => app(LlmClient::class)->chat([Message::user('Hi.')]))
        ->toThrow(LlmRequestException::class, 'No model given');

    Http::assertNothingSent();
});

// ── Responses ────────────────────────────────────────────────────────────────

it('returns the content, model, finish reason and usage', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody('Hello there.'))]);

    $response = app(LlmClient::class)->chat([Message::user('Hi.')]);

    expect($response->content)->toBe('Hello there.')
        ->and($response->model)->toBe('test-model')
        ->and($response->finishReason)->toBe('stop')
        ->and($response->usage->promptTokens)->toBe(120)
        ->and($response->usage->completionTokens)->toBe(340)
        ->and($response->usage->totalTokens)->toBe(460);
});

it('reports zero usage when the endpoint omits it', function () {
    $body = chatCompletionBody();
    unset($body['usage']);
    Http::fake(['llm.test/*' => Http::response($body)]);

    $response = app(LlmClient::class)->chat([Message::user('Hi.')]);

    expect($response->usage->totalTokens)->toBe(0);
});

it('decodes json content', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody('{"title":"Tokyo","days":[]}'))]);

    expect(app(LlmClient::class)->chat([Message::user('Hi.')])->json())
        ->toBe(['title' => 'Tokyo', 'days' => []]);
});

it('decodes json content that arrives wrapped in a markdown fence', function () {
    $fenced = "```json\n{\"title\":\"Tokyo\",\"days\":[]}\n```";
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody($fenced))]);

    expect(app(LlmClient::class)->chat([Message::user('Hi.')])->json())
        ->toBe(['title' => 'Tokyo', 'days' => []]);
});

it('fails when the content is not json', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody('Sorry, I cannot do that.'))]);

    expect(fn () => app(LlmClient::class)->chat([Message::user('Hi.')])->json())
        ->toThrow(LlmResponseException::class, 'could not decode it');
});

it('exposes the raw decoded body for fields it does not model', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody('Hi.', ['system_fingerprint' => 'fp_abc']))]);

    $response = app(LlmClient::class)->chat([Message::user('Hi.')]);

    expect($response->raw['system_fingerprint'])->toBe('fp_abc')
        ->and($response->raw['object'])->toBe('chat.completion');
});

it('strips a markdown fence without decoding the content', function () {
    $fenced = "```json\n{\"title\":\"Tokyo\"}\n```";
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody($fenced))]);

    $response = app(LlmClient::class)->chat([Message::user('Hi.')]);

    expect($response->contentWithoutFences())->toBe('{"title":"Tokyo"}')
        ->and($response->content)->toBe($fenced);
});

it('leaves unfenced content untouched', function () {
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody('Just a sentence.'))]);

    expect(app(LlmClient::class)->chat([Message::user('Hi.')])->contentWithoutFences())
        ->toBe('Just a sentence.');
});

it('fails when the response carries no message content', function () {
    Http::fake(['llm.test/*' => Http::response(['object' => 'chat.completion', 'choices' => []])]);

    expect(fn () => app(LlmClient::class)->chat([Message::user('Hi.')]))
        ->toThrow(LlmResponseException::class, 'returned no message content');
});

// ── Failures ─────────────────────────────────────────────────────────────────

it('raises a rate limit exception on http 429', function () {
    Http::fake(['llm.test/*' => Http::response(
        ['error' => ['message' => 'too many concurrent requests (max 4)']],
        429,
    )]);

    expect(fn () => app(LlmClient::class)->chat([Message::user('Hi.')]))
        ->toThrow(LlmRateLimitException::class, 'too many concurrent requests (max 4)');
});

it('reads retry-after from the response header', function () {
    Http::fake(['llm.test/*' => Http::response(
        ['error' => ['message' => 'slow down']],
        429,
        ['Retry-After' => '12'],
    )]);

    try {
        app(LlmClient::class)->chat([Message::user('Hi.')]);
    } catch (LlmRateLimitException $e) {
        expect($e->retryAfter())->toBe(12);

        return;
    }

    $this->fail('Expected an LlmRateLimitException.');
});

it('reads an http-date retry-after as seconds from now', function () {
    Http::fake(['llm.test/*' => Http::response(
        ['error' => ['message' => 'slow down']],
        429,
        ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 30)],
    )]);

    try {
        app(LlmClient::class)->chat([Message::user('Hi.')]);
    } catch (LlmRateLimitException $e) {
        expect($e->retryAfter())->toBeGreaterThan(25)->toBeLessThanOrEqual(30);

        return;
    }

    $this->fail('Expected an LlmRateLimitException.');
});

it('falls back to a retry-after reported in the body', function () {
    Http::fake(['llm.test/*' => Http::response(
        ['error' => ['message' => 'slow down', 'retry_after' => 7]],
        429,
    )]);

    try {
        app(LlmClient::class)->chat([Message::user('Hi.')]);
    } catch (LlmRateLimitException $e) {
        expect($e->retryAfter())->toBe(7);

        return;
    }

    $this->fail('Expected an LlmRateLimitException.');
});

it('reports no retry-after when the endpoint gives none', function () {
    Http::fake(['llm.test/*' => Http::response(['error' => ['message' => 'slow down']], 429)]);

    try {
        app(LlmClient::class)->chat([Message::user('Hi.')]);
    } catch (LlmRateLimitException $e) {
        expect($e->retryAfter())->toBeNull();

        return;
    }

    $this->fail('Expected an LlmRateLimitException.');
});

it('raises a request exception carrying the status and body on other errors', function () {
    Http::fake(['llm.test/*' => Http::response(
        ['error' => ['message' => 'invalid or missing API key']],
        401,
    )]);

    try {
        app(LlmClient::class)->chat([Message::user('Hi.')]);
    } catch (LlmRequestException $e) {
        expect($e->status)->toBe(401)
            ->and($e->getMessage())->toContain('invalid or missing API key')
            ->and($e->body)->toBe(['error' => ['message' => 'invalid or missing API key']]);

        return;
    }

    $this->fail('Expected an LlmRequestException.');
});

it('describes non-json error bodies instead of swallowing them', function () {
    Http::fake(['llm.test/*' => Http::response('<html>502 Bad Gateway</html>', 502)]);

    expect(fn () => app(LlmClient::class)->chat([Message::user('Hi.')]))
        ->toThrow(LlmRequestException::class, '502 Bad Gateway');
});

it('raises a connection exception when the endpoint is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    expect(fn () => app(LlmClient::class)->chat([Message::user('Hi.')]))
        ->toThrow(LlmConnectionException::class, 'http://llm.test/v1');
});

// ── Models ───────────────────────────────────────────────────────────────────

it('lists the models the endpoint advertises', function () {
    Http::fake(['llm.test/*' => Http::response([
        'object' => 'list',
        'data' => [
            ['id' => 'claude-sonnet-4-5', 'object' => 'model', 'owned_by' => 'anthropic', 'context_length' => 200000],
            ['id' => 'claude-haiku-4-5', 'object' => 'model', 'owned_by' => 'anthropic'],
        ],
    ])]);

    $models = app(LlmClient::class)->models();

    expect($models)->toHaveCount(2)
        ->and($models[0]->id)->toBe('claude-sonnet-4-5')
        ->and($models[0]->ownedBy)->toBe('anthropic')
        ->and($models[0]->contextLength)->toBe(200000)
        ->and($models[1]->contextLength)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://llm.test/v1/models' && $request->method() === 'GET');
});

it('returns an empty list when the catalog is empty', function () {
    Http::fake(['llm.test/*' => Http::response(['object' => 'list', 'data' => []])]);

    expect(app(LlmClient::class)->models())->toBe([]);
});

// ── Wiring ───────────────────────────────────────────────────────────────────

it('resolves the client as a singleton', function () {
    expect(app(LlmClient::class))->toBe(app(LlmClient::class));
});

it('trims a trailing slash from the configured base url', function () {
    config(['llm-client.base_url' => 'http://llm.test/v1/']);
    app()->forgetInstance(LlmClient::class);
    Http::fake(['llm.test/*' => Http::response(chatCompletionBody())]);

    app(LlmClient::class)->chat([Message::user('Hi.')]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://llm.test/v1/chat/completions');
});
