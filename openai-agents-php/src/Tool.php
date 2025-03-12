<?php

namespace OpenAI\Agents;

/**
 * Interface for tools that can be used by agents.
 */
interface Tool
{
    /**
     * Get the name of the tool.
     *
     * @return string The tool name
     */
    public function getName(): string;

    /**
     * Get the description of the tool.
     *
     * @return string The tool description
     */
    public function getDescription(): string;

    /**
     * Get the parameters schema for the tool.
     *
     * @return array The parameters schema in JSON Schema format
     */
    public function getParameters(): array;

    /**
     * Execute the tool with the provided parameters.
     *
     * @param array $parameters The parameters to execute the tool with
     * @return mixed The result of the tool execution
     */
    public function execute(array $parameters): mixed;
}