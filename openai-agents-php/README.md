# OpenAI Agents SDK for PHP

A PHP port of the [OpenAI Agents Python SDK](https://github.com/openai/openai-agents-python), providing a lightweight yet powerful framework for building multi-agent workflows in PHP 8.1+.

## Features

- Create agents with instructions, tools, and model settings
- Define function-based tools with automatic JSON Schema generation
- Support for agent-to-agent handoffs
- Synchronous and asynchronous execution
- Streaming responses
- Comprehensive tracing

## Requirements

- PHP 8.1 or higher
- Composer
- OpenAI API key

## Installation

```bash
composer require openai/agents
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use OpenAI\Agents\Agent;
use OpenAI\Agents\Runner;

// Create a simple agent
$agent = new Agent(
    name: "Assistant",
    instructions: "You are a helpful assistant"
);

// Run the agent with input
$result = Runner::runSync($agent, "Write a haiku about recursion in programming.");
echo $result->getFinalOutputAsString();

// Expected output:
// Code within code,
// Functions calling themselves,
// Infinite loop's dance.
```

## Function Tools Example

```php
<?php

require_once 'vendor/autoload.php';

use OpenAI\Agents\Agent;
use OpenAI\Agents\FunctionTool;
use OpenAI\Agents\Runner;

// Define a function for the tool
function getWeather(string $city): string {
    return "The weather in {$city} is sunny and 72 degrees.";
}

// Create a function tool
$weatherTool = new FunctionTool('getWeather');

// Create an agent with the tool
$agent = new Agent(
    name: "Weather Assistant",
    instructions: "You are a helpful assistant that can tell people the weather.",
    tools: [$weatherTool]
);

// Run the agent
$result = Runner::runSync($agent, "What's the weather like in Tokyo today?");
echo $result->getFinalOutputAsString();
```

## Development

1. Clone the repository
2. Install dependencies with `composer install`
3. Run tests with `vendor/bin/phpunit`

## Documentation

See the full documentation at [docs/](docs/) for more details on:

- Agent configuration
- Creating custom tools
- Handling handoffs
- Async execution
- Guardrails
- Tracing

## License

MIT