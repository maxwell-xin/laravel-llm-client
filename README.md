# laravel-llm-client

A small Laravel client for **OpenAI-compatible** chat-completion endpoints — a self-hosted proxy, a gateway such as LiteLLM or OpenRouter, or OpenAI itself. Anything that answers `POST /v1/chat/completions` in the OpenAI wire format.

It is deliberately a transport client: it sends messages and hands back the completion. Prompt wording, output schemas and domain validation stay in your application — the package only supplies a `PromptBuilder` contract to keep those prompts in a consistent shape.

## Requirements

- PHP 8.3+
- Laravel 13.x (`illuminate/http` and `illuminate/support` ^13.0)

## Install

```bash
composer require maxcloudapps/laravel-llm-client
```

> **Not on Packagist yet.** Until it is published, point Composer at the repository first. In your Laravel app's `composer.json`:
>
> ```json
> "repositories": [
>     { "type": "vcs", "url": "https://github.com/maxwell-xin/laravel-llm-client" }
> ]
> ```
>
> then `composer require maxcloudapps/laravel-llm-client:dev-main`.

The service provider and the `Llm` facade are registered automatically — there is nothing to add to `config/app.php` or `bootstrap/providers.php`.

Publish the config only if you want to edit the defaults directly, rather than through `.env`:

```bash
php artisan vendor:publish --tag=llm-client-config
```

That writes `config/llm-client.php`.

<details>
<summary>Developing the package alongside an app</summary>

Point the app at your local checkout instead of GitHub, and edits show up without reinstalling:

```json
"repositories": [
  {
    "type": "path",
    "url": "../laravel-llm-client",
    "options": {
      "symlink": true
    }
  }
]
```

Then `composer require maxcloudapps/laravel-llm-client:@dev`.
</details>

## Configure

Add these to your app's `.env`:

```dotenv
LLM_BASE_URL=https://api.openai.com/v1   # required in practice — see below
LLM_MODEL=gpt-4o-mini                    # required — any model id your endpoint advertises
LLM_API_KEY=your-key                     # omit entirely for endpoints that do not authenticate
LLM_TIMEOUT=600                          # optional, seconds to wait for a completion
LLM_CONNECT_TIMEOUT=10                   # optional, seconds to establish the connection
```

Only two really need your attention:

- **`LLM_MODEL` has no default.** Without it, every call fails with `LlmRequestException` before a request is even sent. Set it, or pass `model:` on each call.
- **`LLM_BASE_URL` defaults to `http://127.0.0.1:8080/v1`**, which assumes a gateway running on your own machine. Set it to whatever you are actually talking to — `https://api.openai.com/v1`, your LiteLLM/OpenRouter URL, or your own proxy. Include the version segment; endpoints are appended to it.

`LLM_TIMEOUT` defaults to 600 rather than Laravel's usual 30, because generation is slow. `LLM_CONNECT_TIMEOUT` stays small so an endpoint that is simply down fails fast.

## Verify the install

```bash
php artisan tinker
```

```php
Llm::models();                                  // endpoint reachable and key accepted?
Llm::prompt('Say hi in five words.')->content;  // model id valid?
```

The first call proves the URL and key are right; the second proves the model id is. If either throws:

| What you see                                                    | What to fix                                                           |
|-----------------------------------------------------------------|-----------------------------------------------------------------------|
| `LlmConnectionException`                                        | `LLM_BASE_URL` — wrong host, wrong port, or nothing listening.        |
| `LlmRequestException` with `$status === 401` / `403`            | `LLM_API_KEY`.                                                        |
| `LlmRequestException` with `$status === 404`                    | `LLM_BASE_URL` is missing its `/v1` segment.                          |
| `LlmRequestException` with `$status === 0`                      | `LLM_MODEL` is not set.                                               |
| `LlmRequestException` with `$status === 400` mentioning a model | The model id is not one this endpoint serves — check `Llm::models()`. |

Run `php artisan config:clear` if you changed `.env` and nothing seems to take effect.

## Use

```php
use MaxCloudApps\LlmClient\Facades\Llm;

$response = Llm::prompt(
    prompt: 'Plan three days in Tokyo.',
    system: 'You are an expert travel planner.',
);

$response->content;               // string
$response->model;                 // string
$response->finishReason;          // 'stop', 'length', … or null
$response->usage->promptTokens;   // int
$response->usage->totalTokens;    // int
$response->raw;                   // the full decoded body, for fields this object does not model
```

`prompt()` is the shortcut for a single turn whose text you wrote yourself. Both halves reach the endpoint exactly as given, so the moment the text carries caller-supplied data — a comment to summarise, a question typed by a user — build it with a [`PromptBuilder`](#prompt-builders) and call `chat()`, which keeps that data in the user half where it reads as data rather than as instructions.

`ChatResponse` is readonly, and `usage` falls back to zeros rather than failing when an endpoint omits it — so `$response->usage->totalTokens === 0` means "not reported", not "free".

Multi-turn, with a per-call model and pass-through options:

```php
use MaxCloudApps\LlmClient\Data\Message;

$response = Llm::chat(
    messages: [
        Message::system('You are an expert travel planner.'),
        Message::user('Plan three days in Tokyo.'),
        Message::assistant('Day 1: …'),
        Message::user('Make day 2 cheaper.'),
    ],
    model: 'claude-haiku-4-5',
    options: ['temperature' => 0.4],
);
```

`options` is merged into the request body untouched, so anything the OpenAI API accepts (`temperature`, `max_tokens`, `response_format`, …) can be passed. Endpoints ignore what they do not support — check yours before relying on a field. `model` and `messages` are always set by the client and cannot be overridden through `options`.

Prefer injecting the client where you have a constructor to inject it into; the facade and the injected instance are the same singleton:

```php
use MaxCloudApps\LlmClient\LlmClient;

public function __construct(private readonly LlmClient $llm) {}
```

## Prompt builders

The client takes messages, not prompts. `PromptBuilder` is the shape to build them in: a trusted system half that never varies, and a user half carrying this request's data.

```php
use MaxCloudApps\LlmClient\Facades\Llm;
use MaxCloudApps\LlmClient\Prompts\BuildsMessages;
use MaxCloudApps\LlmClient\Prompts\PromptBuilder;

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

Llm::chat((new GreetingPromptBuilder('Ada'))->messages());
```

`BuildsMessages` supplies `messages()` as a system turn followed by a user turn. Write your own when a prompt needs more turns, such as few-shot examples between the two.

Build one per request. Anything both halves must agree on — a boundary marker wrapping untrusted text, the output shape you are asking for — belongs in the constructor, where it cannot drift between them. Never interpolate caller-supplied text into `systemInstruction()`; that is what `userPrompt()` is for.

## JSON responses

```php
$itinerary = Llm::chat((new ItineraryPromptBuilder($city))->messages())->json();
```

`json()` is a method on `ChatResponse`, so it works the same on anything `chat()` or `prompt()` returns. It strips a surrounding ```` ```json ```` fence before decoding — models add one even when told not to — and throws `LlmResponseException` if the content still will not decode. Use `contentWithoutFences()` when you want the unfenced text without decoding it.

An endpoint without real structured-output support cannot *guarantee* the shape, so validate the decoded array in your application before trusting it.

## Listing models

```php
foreach (Llm::models() as $model) {
    $model->id;             // 'claude-sonnet-4-5'
    $model->ownedBy;        // 'anthropic' or null
    $model->contextLength;  // int or null
}
```

## Errors

All exceptions extend `MaxCloudApps\LlmClient\Exceptions\LlmException`:

| Exception                | Meaning                                                                            |
|--------------------------|------------------------------------------------------------------------------------|
| `LlmConnectionException` | The endpoint could not be reached at all.                                          |
| `LlmRequestException`    | The endpoint answered non-2xx. Carries `$status` and `$body`.                      |
| `LlmRateLimitException`  | HTTP 429. Extends `LlmRequestException`; `retryAfter()` when the endpoint says so. |
| `LlmResponseException`   | 2xx, but the body had no usable content or would not decode as JSON.               |

`LlmRequestException` is also raised *before* any request is sent when no model is given and none is configured; that case carries `$status === 0`.

`LlmRateLimitException` is separate because it is the one failure expected to succeed later without anything changing — retry it rather than failing the job:

```php
try {
    $response = Llm::prompt($prompt);
} catch (LlmRateLimitException $e) {
    $this->release($e->retryAfter() ?? 30);
}
```

`retryAfter()` reads the standard `Retry-After` response header, in either of the forms RFC 9110 allows — a number of seconds, or an HTTP date, which is converted to seconds from now. Endpoints that report the wait in the JSON body instead (`error.retry_after` or `retry_after`) are handled as a fallback. It returns `null` when the endpoint said nothing, so pick your own default as above.

## Testing your app

The client resolves `Illuminate\Http\Client\Factory` from the container, which is the same instance the `Http` facade uses — so `Http::fake()` intercepts its calls in your app's tests, with no need to mock the facade:

```php
Http::fake(['llm.test/*' => Http::response([
    'model' => 'test-model',
    'choices' => [['message' => ['role' => 'assistant', 'content' => '{"ok":true}']]],
    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20, 'total_tokens' => 30],
])]);
```

Match the pattern against whatever `LLM_BASE_URL` is in your test environment.

## Developing this package

The test suite runs in a pinned PHP container, so nothing has to be installed on the host. The container installs dependencies on boot and then idles, so the suite can be run against it as often as you like:

```bash
docker compose up -d                      # start it
docker compose exec test vendor/bin/pest  # run the suite
docker compose exec test vendor/bin/pint  # format
docker compose down                       # stop it
```

Or locally, with PHP 8.3+ and Composer:

```bash
composer install
composer test        # pest
composer lint        # pint
composer lint:check  # pint --test
```

## Notes

- The client holds no per-request state, so it is safe as a singleton under Octane.
- Streaming is not supported; every call is a single blocking request.

## License

MIT.
