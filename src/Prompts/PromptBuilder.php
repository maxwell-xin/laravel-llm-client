<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Prompts;

use MaxCloudApps\LlmClient\Data\Message;

/**
 * Builds the two halves of a chat prompt: the trusted instruction the model
 * always follows, and the message carrying one request's data.
 *
 * Keeping the halves apart is what makes a prompt safe to reuse. The system
 * half is written by you and is identical on every call; the user half carries
 * whatever the caller supplied, including free text you cannot trust.
 *
 * Implementations are built per request, so anything the two halves must agree
 * on — a boundary marker wrapping untrusted text, the output shape being asked
 * for — is fixed once in the constructor and cannot drift between them.
 */
interface PromptBuilder
{
    /**
     * The model's fixed job description: its role, the rules it must always
     * follow, and the shape its answer must take.
     *
     * Trusted text only. Never interpolate caller-supplied values here — that is
     * what userPrompt() is for.
     */
    public function systemInstruction(): string;

    /**
     * The message for this one request.
     *
     * Put plain facts here, and wrap any free text the caller supplied in a
     * clearly marked block so the model reads it as data rather than as
     * instructions it should obey.
     */
    public function userPrompt(): string;

    /**
     * Both halves, in the order LlmClient::chat() expects.
     *
     * @return list<Message>
     */
    public function messages(): array;
}
