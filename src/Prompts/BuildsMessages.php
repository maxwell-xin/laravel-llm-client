<?php

declare(strict_types=1);

namespace MaxCloudApps\LlmClient\Prompts;

use MaxCloudApps\LlmClient\Data\Message;

/**
 * The only implementation of PromptBuilder::messages() most builders need:
 * the system turn first, the user turn second.
 *
 * Write messages() by hand instead when a prompt genuinely needs more turns,
 * such as few-shot examples between the two.
 */
trait BuildsMessages
{
    /**
     * @return list<Message>
     */
    public function messages(): array
    {
        return [
            Message::system($this->systemInstruction()),
            Message::user($this->userPrompt()),
        ];
    }

    abstract public function systemInstruction(): string;

    abstract public function userPrompt(): string;
}
