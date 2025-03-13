<?php

namespace OpenAI\Agents;

/**
 * Represents the final result of running an agent.
 */
class Result
{
    /**
     * Create a new result.
     *
     * @param string|array|null $finalOutput The final output from the agent
     * @param array $messages The complete message history
     * @param array $steps The steps taken during agent execution
     * @param array $usage Token usage statistics
     */
    public function __construct(
        public readonly mixed $finalOutput,
        public readonly array $messages,
        public array $steps = [],
        public array $usage = []
    ) {
    }
    
    /**
     * Get the final output as a string.
     *
     * @return string The final output as a string
     */
    public function getFinalOutputAsString(): string
    {
        if (is_string($this->finalOutput)) {
            return $this->finalOutput;
        }
        
        if (is_array($this->finalOutput) || is_object($this->finalOutput)) {
            return json_encode($this->finalOutput, JSON_PRETTY_PRINT);
        }
        
        return (string)$this->finalOutput;
    }
    
    /**
     * Get the final agent that produced the output.
     *
     * @return Agent|null The final agent or null if steps is empty
     */
    public function getFinalAgent(): ?Agent
    {
        if (empty($this->steps)) {
            return null;
        }
        
        $lastStep = end($this->steps);
        return $lastStep['agent'] ?? null;
    }
    
    /**
     * Get the total token usage.
     *
     * @return array The token usage totals
     */
    public function getTotalUsage(): array
    {
        $total = [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0
        ];
        
        foreach ($this->usage as $usage) {
            $total['prompt_tokens'] += $usage['prompt_tokens'] ?? 0;
            $total['completion_tokens'] += $usage['completion_tokens'] ?? 0;
            $total['total_tokens'] += $usage['total_tokens'] ?? 0;
        }
        
        return $total;
    }
}