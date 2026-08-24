<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MaxCloudApps\LlmClient\Data\ChatResponse;
use MaxCloudApps\LlmClient\Data\Message;
use MaxCloudApps\LlmClient\Data\Model;
use MaxCloudApps\LlmClient\Exceptions\LlmConnectionException;
use MaxCloudApps\LlmClient\Exceptions\LlmRateLimitException;
use MaxCloudApps\LlmClient\Exceptions\LlmRequestException;

/**
 * Talks to any endpoint implementing the OpenAI chat-completions API.
 *
 * Holds no per-request state, so it is safe to keep as a singleton under a
 * long-running server such as Octane.
 */
readonly class LlmClient
{
    public function __construct(
        protected HttpFactory $http,
        protected string $baseUrl,
        protected ?string $apiKey = null,
        protected ?string $model = null,
        protected int $timeout = 600,
        protected int $connectTimeout = 10,
    ) {}

    /**
     * Send a conversation and return the completion.
     *
     * @param  list<Message>  $messages
     * @param  array<string, mixed>  $options  Extra request fields passed through untouched
     *                                         (temperature, max_tokens, response_format, …).
     *                                         Endpoints silently ignore what they do not support.
     */
    public function chat(array $messages, ?string $model = null, array $options = []): ChatResponse
    {
        $model ??= $this->model;

        if ($model === null || $model === '') {
            throw new LlmRequestException(
                'No model given and no default configured. Set llm-client.model or pass one to chat().'
            );
        }

        $payload = [
            ...$options,
            'model' => $model,
            'messages' => array_map(static fn (Message $message): array => $message->toArray(), $messages),
        ];

        return ChatResponse::from($this->send('chat/completions', $payload));
    }

    /**
     * Shortcut for a single-turn call whose text you wrote yourself.
     *
     * Both halves go to the endpoint exactly as given, so interpolating
     * caller-supplied data into $prompt hands that data to the model as
     * instructions. Build the two halves with a PromptBuilder and call chat()
     * instead whenever the text carries anything you did not write.
     *
     * @param  array<string, mixed>  $options
     */
    public function prompt(string $prompt, ?string $system = null, ?string $model = null, array $options = []): ChatResponse
    {
        $messages = $system === null
            ? [Message::user($prompt)]
            : [Message::system($system), Message::user($prompt)];

        return $this->chat($messages, $model, $options);
    }

    /**
     * The models this endpoint advertises.
     *
     * @return list<Model>
     *
     * @throws LlmConnectionException|LlmRequestException
     */
    public function models(): array
    {
        $data = $this->send('models', method: 'get');

        $models = $data['data'] ?? [];

        if (! is_array($models)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $model): Model => Model::from($model),
            array_filter($models, 'is_array'),
        ));
    }

    /**
     * Perform the request and hand back the decoded body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws LlmConnectionException|LlmRequestException
     */
    protected function send(string $endpoint, array $payload = [], string $method = 'post'): array
    {
        try {
            $response = $method === 'get'
                ? $this->request()->get($endpoint)
                : $this->request()->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            throw LlmConnectionException::to($this->baseUrl, $e);
        }

        if ($response->failed()) {
            throw $this->failure($response);
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    protected function request(): PendingRequest
    {
        $request = $this->http
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->acceptJson()
            ->asJson();

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $request = $request->withToken($this->apiKey);
        }

        return $request;
    }

    protected function failure(Response $response): LlmRequestException
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        return $response->status() === 429
            ? LlmRateLimitException::fromResponse(
                $response->status(),
                $body,
                $response->body(),
                // '' when the endpoint sent no such header.
                $response->header('Retry-After') ?: null,
            )
            : LlmRequestException::fromResponse($response->status(), $body, $response->body());
    }
}
