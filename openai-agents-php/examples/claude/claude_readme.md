# Using Claude Models with PHP Agents SDK

This directory contains examples of how to use Anthropic's Claude models with the PHP Agents SDK.

## Setup

1. Make sure you have an Anthropic API key. You can get one at https://console.anthropic.com/
2. Set the API key as an environment variable:
   ```bash
   export ANTHROPIC_API_KEY="your-api-key-here"
   ```

## Examples

The following examples demonstrate how to use Claude models:

### 1. Basic Weather Example (claude_basic.php)

A simple example that uses Claude to check the weather in a location.

```bash
php openai-agents-php/examples/claude_basic.php
```

### 2. Interactive Demo (claude_demo.php)

An interactive chat example with a shopping assistant that can search products and calculate taxes.

```bash
php openai-agents-php/examples/claude_demo.php
```

## Available Claude Models

- `claude-3-5-sonnet-20240620`
- `claude-3-haiku-20240307`
- `claude-3-opus-20240229`
- `claude-3-sonnet-20240229`
- `claude-3-7-sonnet-latest` (newest model)

## Implementation Details

The `AnthropicModel` class extends the SDK's `ModelInterface` and handles:

1. Converting messages between OpenAI and Anthropic formats
2. Processing tool usage requests from Claude
3. Handling tool responses and returning them to Claude
4. Converting Claude's final responses to the expected format

The implementation maps tool calls between the different APIs to ensure compatibility with the existing agent framework.

## Key Differences from OpenAI

Anthropic's Claude API has a different approach to tools than OpenAI:

1. Claude uses a different message format for tools
2. Tool calls use `tool_use` and `tool_result` types
3. Claude's API requires specific formatting for tool results

The implementation handles these differences transparently, so you can use Claude models just like OpenAI models in your agent applications.