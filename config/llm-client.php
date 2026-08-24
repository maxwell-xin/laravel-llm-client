<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The root of the OpenAI-compatible API, including the version segment.
    | Endpoints are appended to it, so "/v1" here becomes "/v1/chat/completions".
    */

    'base_url' => env('LLM_BASE_URL', 'http://127.0.0.1:8080/v1'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Sent as a bearer token. Leave empty for endpoints that do not
    | authenticate, and no Authorization header will be sent at all.
    */

    'api_key' => env('LLM_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | Used whenever a call does not name a model of its own. Requests fail with
    | an LlmRequestException when neither this nor the call specifies one.
    */

    'model' => env('LLM_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | "timeout" is the seconds to wait for a completed response — generation is
    | slow, so this is deliberately far above Laravel's 30 second default.
    | "connect_timeout" only covers establishing the connection, so a dead
    | endpoint fails fast instead of hanging for the full request timeout.
    */

    'timeout' => (int) env('LLM_TIMEOUT', 600),

    'connect_timeout' => (int) env('LLM_CONNECT_TIMEOUT', 10),

];
