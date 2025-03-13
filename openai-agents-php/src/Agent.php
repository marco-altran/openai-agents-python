<?php

namespace OpenAI\Agents;

/**
 * Represents an agent powered by a language model.
 */
class Agent
{
    /**
     * Create a new agent.
     *
     * @param string $name Agent name
     * @param string $instructions System instructions for the agent
     * @param Tool[]|null $tools Tools available to the agent
     * @param Agent[]|null $handoffs Other agents this agent can hand off to
     * @param array|null $guardrails Input/output guardrails for validation
     * @param string|null $outputType Expected structured output type (JSON schema)
     * @param ModelSettings|null $modelSettings Model configuration
     */
    public function __construct(
        public string $name,
        public string $instructions,
        public ?array $tools = null,
        public ?array $handoffs = null,
        public ?array $guardrails = null,
        public ?string $outputType = null,
        public ?ModelSettings $modelSettings = null
    ) {
        // Initialize empty arrays when null
        $this->tools = $this->tools ?? [];
        $this->handoffs = $this->handoffs ?? [];
        $this->guardrails = $this->guardrails ?? [];
        
        // Set default model settings if none provided
        $this->modelSettings = $this->modelSettings ?? new ModelSettings(
            model: 'gpt-4-turbo-preview',
            temperature: 0.7
        );
        
        // Validate tool types
        foreach ($this->tools as $tool) {
            if (!$tool instanceof Tool) {
                throw new \InvalidArgumentException(
                    'All tools must implement the Tool interface. Got: ' . (is_object($tool) ? get_class($tool) : gettype($tool))
                );
            }
        }
        
        // Validate handoff types
        foreach ($this->handoffs as $handoff) {
            if (!$handoff instanceof Agent) {
                throw new \InvalidArgumentException(
                    'All handoffs must be Agent instances. Got: ' . (is_object($handoff) ? get_class($handoff) : gettype($handoff))
                );
            }
        }
    }
    
    /**
     * Get the system prompt for this agent.
     *
     * @return string The system prompt
     */
    public function getSystemPrompt(): string
    {
        return $this->instructions;
    }
    
    /**
     * Get the tools configuration in the format required by the model.
     *
     * @return array The tools configuration
     */
    public function getToolsConfig(): array
    {
        $toolsConfig = [];
        
        foreach ($this->tools as $tool) {
            $toolsConfig[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters' => $tool->getParameters(),
                ]
            ];
        }
        
        return $toolsConfig;
    }
    
    /**
     * Find a tool by name.
     *
     * @param string $name The tool name to search for
     * @return Tool|null The tool if found, null otherwise
     */
    public function findTool(string $name): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->getName() === $name) {
                return $tool;
            }
        }
        
        return null;
    }
    
    /**
     * Find a handoff agent by name.
     *
     * @param string $name The agent name to search for
     * @return Agent|null The agent if found, null otherwise
     */
    public function findHandoff(string $name): ?Agent
    {
        foreach ($this->handoffs as $agent) {
            if ($agent->name === $name) {
                return $agent;
            }
        }
        
        return null;
    }
}