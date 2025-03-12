<?php

namespace OpenAI\Agents;

/**
 * Settings for configuring the model used by an agent.
 */
class ModelSettings
{
    /**
     * Create model settings with the specified parameters.
     *
     * @param string|null $model The model identifier to use (e.g., "gpt-4")
     * @param float|null $temperature Model temperature setting between 0 and 2
     * @param int|null $maxTokens Maximum number of tokens to generate
     * @param array|null $additionalArgs Additional arguments to pass to the model
     */
    public function __construct(
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?array $additionalArgs = null
    ) {
        // Validate temperature range if provided
        if ($this->temperature !== null && ($this->temperature < 0 || $this->temperature > 2)) {
            throw new \InvalidArgumentException("Temperature must be between 0 and 2");
        }

        // Validate maxTokens if provided
        if ($this->maxTokens !== null && $this->maxTokens <= 0) {
            throw new \InvalidArgumentException("Max tokens must be greater than 0");
        }
    }

    /**
     * Convert the model settings to an array for API requests.
     *
     * @return array The model settings as an array
     */
    public function toArray(): array
    {
        $settings = [];

        if ($this->model !== null) {
            $settings['model'] = $this->model;
        }

        if ($this->temperature !== null) {
            $settings['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $settings['max_tokens'] = $this->maxTokens;
        }

        if ($this->additionalArgs !== null) {
            $settings = array_merge($settings, $this->additionalArgs);
        }

        return $settings;
    }
}