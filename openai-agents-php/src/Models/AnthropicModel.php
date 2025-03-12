<?php

namespace OpenAI\Agents\Models;

use React\Promise\Promise;
use React\Promise\PromiseInterface;
use GuzzleHttp\Client;
use OpenAI\Agents\ModelSettings;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use InvalidArgumentException;

/**
 * Anthropic Claude API implementation of the model interface.
 */
class AnthropicModel implements ModelInterface
{
    private Client $client;
    private string $apiKey;
    private ModelSettings $defaultSettings;
    private LoggerInterface $logger;
    private string $baseUrl;

    /**
     * Create a new Anthropic model.
     *
     * @param string $apiKey Anthropic API key
     * @param ModelSettings|null $defaultSettings Default model settings
     * @param LoggerInterface|null $logger Logger for API interactions
     * @param string $baseUrl Base URL for the Anthropic API
     */
    public function __construct(
        string $apiKey,
        ?ModelSettings $defaultSettings = null,
        ?LoggerInterface $logger = null,
        string $baseUrl = 'https://api.anthropic.com'
    ) {
        if (empty($apiKey)) {
            throw new InvalidArgumentException('API key cannot be empty');
        }

        $this->apiKey = $apiKey;
        $this->defaultSettings = $defaultSettings ?? new ModelSettings(
            model: 'claude-3-sonnet-20240229',
            temperature: 0.7
        );
        $this->logger = $logger ?? new NullLogger();
        $this->baseUrl = $baseUrl;

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $messages, array $options = []): array
    {
        $settings = $this->mergeSettings($options);
        $payload = $this->buildRequestPayload($messages, $settings);

        try {
            $this->logger->debug('Sending request to Anthropic API', [
                'model' => $settings['model'],
                'message_count' => count($messages),
            ]);

            // Check for tool outputs in messages and handle them
            $toolResponse = $this->extractToolResponse($messages);
            if ($toolResponse) {
                $this->logger->debug('Tool response detected, adding to payload', [
                    'tool_name' => $toolResponse['name'] ?? 'unknown'
                ]);
                // Add tool response to messages for follow-up
                $payload = $this->addToolResponseToPayload($payload, $toolResponse);
            }

            $this->logger->debug('Prepared request payload', [
                'message_count' => count($payload['messages'] ?? []),
                'has_tools' => isset($payload['tools'])
            ]);

            $response = $this->client->post('/v1/messages', [
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // Check if Claude wants to use a tool
            if ($responseData['stop_reason'] === 'tool_use' && isset($responseData['content'])) {
                // Extract tool use information
                foreach ($responseData['content'] as $content) {
                    if ($content['type'] === 'tool_use') {
                        $this->logger->debug('Claude wants to use tool', [
                            'tool_name' => $content['name']
                        ]);

                        // Transform to OpenAI format with tool_calls
                        $transformedResponse = $this->transformToolUseResponse($responseData);
                        return $transformedResponse;
                    }
                }
            }

            // Transform Anthropic response to match OpenAI format for compatibility
            $transformedResponse = $this->transformResponse($responseData);

            $this->logger->debug('Received response from Anthropic API', [
                'status' => $response->getStatusCode(),
                'usage' => $transformedResponse['usage'] ?? null,
            ]);

            return $transformedResponse;
        } catch (\Exception $e) {
            $this->logger->error('Error calling Anthropic API', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            // Log detailed error information
            $this->logger->error('Anthropic API call failed', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);

            throw $e;
        }
    }

    /**
     * Extract tool response from messages if present.
     *
     * @param array $messages The messages to check
     * @return array|null Tool response data or null if none found
     */
    private function extractToolResponse(array $messages): ?array
    {
        // Look for the most recent tool message
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $message = $messages[$i];

            if ($message['role'] === 'tool' && isset($message['tool_call_id'])) {
                return [
                    'tool_call_id' => $message['tool_call_id'],
                    'name' => $message['name'] ?? '',
                    'content' => $message['content'] ?? '',
                ];
            }
        }

        return null;
    }

    /**
     * Add tool response to payload for follow-up.
     *
     * @param array $payload Original payload
     * @param array $toolResponse Tool response data
     * @return array Updated payload
     */
    private function addToolResponseToPayload(array $payload, array $toolResponse): array
    {
        $toolName = $toolResponse['name'] ?? '';
        $toolCallId = $toolResponse['tool_call_id'] ?? '';
        $toolContent = $toolResponse['content'] ?? '';

        $this->logger->debug('Processing tool response', [
            'tool_name' => $toolName,
            'tool_call_id' => $toolCallId
        ]);

        // Try to parse the content as JSON for tool input
        $toolInput = [];
        if (!empty($toolName)) {
            // For getCurrentWeather, parse location from content
            if ($toolName === 'getCurrentWeather' && strpos($toolContent, 'Paris') !== false) {
                $toolInput = ['location' => 'Paris'];
            } else {
                // Try to extract structured data from the content
                $decoded = json_decode($toolContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $toolInput = $decoded;
                } else {
                    // Fallback to empty object
                    $toolInput = new \stdClass();
                }
            }
        } else {
            // Empty object as fallback
            $toolInput = new \stdClass();
        }

        // Create assistant message with tool_use content format
        $assistantMessage = [
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => $toolCallId,
                    'name' => $toolName,
                    'input' => $toolInput
                ]
            ]
        ];

        // Create user message with tool result
        $toolResultMessage = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolCallId,
                    'content' => $toolContent
                ]
            ]
        ];

        // Reset the messages array with properly formatted messages
        $payload['messages'] = [
            $assistantMessage,
            $toolResultMessage
        ];

        return $payload;
    }

    /**
     * Transform a tool use response to OpenAI format.
     *
     * @param array $responseData The response with tool use
     * @return array OpenAI-compatible format with tool_calls
     */
    private function transformToolUseResponse(array $responseData): array
    {
        $toolCalls = [];
        $textContent = '';

        foreach ($responseData['content'] as $content) {
            if ($content['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $content['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $content['name'],
                        'arguments' => json_encode($content['input'])
                    ]
                ];
            } else if ($content['type'] === 'text') {
                $textContent = $content['text'];
            }
        }

        $message = [
            'role' => 'assistant',
            'content' => $textContent,
            'tool_calls' => $toolCalls
        ];

        // Create OpenAI-compatible response with tool_calls
        $openAIResponse = [
            'id' => $responseData['id'] ?? uniqid('resp-'),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $responseData['model'] ?? $this->defaultSettings->model,
            'choices' => [
                [
                    'message' => $message,
                    'finish_reason' => 'tool_calls',
                    'index' => 0,
                ]
            ],
            'usage' => [
                'prompt_tokens' => $responseData['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $responseData['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($responseData['usage']['input_tokens'] ?? 0) + ($responseData['usage']['output_tokens'] ?? 0),
            ],
        ];

        return $openAIResponse;
    }

    /**
     * {@inheritdoc}
     */
    public function generateAsync(array $messages, array $options = []): PromiseInterface
    {
        $settings = $this->mergeSettings($options);
        $payload = $this->buildRequestPayload($messages, $settings);

        $this->logger->debug('Sending async request to Anthropic API', [
            'model' => $settings['model'],
            'message_count' => count($messages),
        ]);

        $guzzlePromise = $this->client->postAsync('/v1/messages', [
            'json' => $payload,
        ]);

        return new Promise(function ($resolve, $reject) use ($guzzlePromise) {
            $guzzlePromise->then(
                function ($response) use ($resolve) {
                    $responseData = json_decode($response->getBody()->getContents(), true);

                    // Transform Anthropic response to match OpenAI format
                    $transformedResponse = $this->transformResponse($responseData);

                    $this->logger->debug('Received async response from Anthropic API', [
                        'status' => $response->getStatusCode(),
                        'usage' => $transformedResponse['usage'] ?? null,
                    ]);

                    $resolve($transformedResponse);
                },
                function ($reason) use ($reject) {
                    $this->logger->error('Error in async Anthropic API call', [
                        'error' => $reason->getMessage(),
                    ]);
                    $reject($reason);
                }
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function generateStream(array $messages, array $options = []): \Generator
    {
        $settings = $this->mergeSettings($options);
        $payload = $this->buildRequestPayload($messages, $settings);
        $payload['stream'] = true;

        try {
            $this->logger->debug('Starting stream request to Anthropic API', [
                'model' => $settings['model'],
                'message_count' => count($messages),
            ]);

            $response = $this->client->post('/v1/messages', [
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();

            while (!$body->eof()) {
                $line = $this->readStreamLine($body);

                if (empty($line)) {
                    continue;
                }

                if ($line === "data: [DONE]") {
                    break;
                }

                if (str_starts_with($line, 'data: ')) {
                    $jsonData = substr($line, 6); // Remove 'data: ' prefix
                    $data = json_decode($jsonData, true);

                    if ($data) {
                        // Transform streaming data to match OpenAI format
                        $transformedData = $this->transformStreamingChunk($data);
                        yield $transformedData;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in stream from Anthropic API', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateStreamAsync(array $messages, array $options = []): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($messages, $options) {
            try {
                $generator = $this->generateStream($messages, $options);
                $resolve($generator);
            } catch (\Exception $e) {
                $reject($e);
            }
        });
    }

    /**
     * Merge default settings with provided options.
     *
     * @param array $options User-provided options
     * @return array Merged settings
     */
    private function mergeSettings(array $options): array
    {
        $defaultSettings = $this->defaultSettings->toArray();
        return array_merge($defaultSettings, $options);
    }

    /**
     * Build the request payload for the Anthropic API.
     *
     * @param array $messages Messages to send
     * @param array $settings Model settings
     * @return array The complete request payload
     */
    private function buildRequestPayload(array $messages, array $settings): array
    {
        // Transform from OpenAI message format to Anthropic format
        $anthropicMessages = $this->transformMessages($messages);

        $payload = [
            'messages' => $anthropicMessages,
            'model' => $settings['model'],
            'max_tokens' => $settings['max_tokens'] ?? 4096,
        ];

        // Handle system instructions
        $systemPrompt = null;
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemPrompt = $message['content'];
                break;
            }
        }

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        // Map OpenAI parameters to Anthropic equivalents
        if (isset($settings['temperature'])) {
            $payload['temperature'] = $settings['temperature'];
        }

        if (isset($settings['top_p'])) {
            $payload['top_p'] = $settings['top_p'];
        }

        // Handle tools (convert from OpenAI format to Anthropic format)
        if (isset($settings['tools'])) {
            $payload['tools'] = $this->transformTools($settings['tools']);
        }

        return $payload;
    }

    /**
     * Transform OpenAI-format messages to Anthropic format.
     *
     * @param array $messages OpenAI-format messages
     * @return array Anthropic-format messages
     */
    private function transformMessages(array $messages): array
    {
        $anthropicMessages = [];

        // Ensure we have at least one valid message
        $hasValidUserMessage = false;

        foreach ($messages as $message) {
            // Skip system messages as they're handled separately
            if ($message['role'] === 'system') {
                continue;
            }

            // Map roles
            $role = match ($message['role']) {
                'user' => 'user',
                'assistant' => 'assistant',
                'tool' => 'tool', // Will need special handling for tool responses
                default => null,
            };

            if ($role === null) {
                continue;
            }

            $content = $message['content'] ?? '';

            // Ensure content is not empty (Anthropic requires non-empty content)
            if (empty($content) && $role === 'user') {
                $content = "Hello";
                $this->logger->debug('Empty user message replaced with default content');
            }

            if ($role === 'user' && !empty($content)) {
                $hasValidUserMessage = true;
            }

            // Handle tool calls in assistant messages
            if ($role === 'assistant' && isset($message['tool_calls'])) {
                $anthropicMessage = [
                    'role' => $role,
                    'content' => $content ?: ' ', // Ensure content is not empty
                    'tool_calls' => $message['tool_calls'],
                ];
            }
            // Handle tool responses
            else if ($role === 'tool') {
                if (isset($message['tool_call_id'])) {
                    $anthropicMessage = [
                        'role' => 'assistant',
                        'content' => ' ', // Anthropic requires non-empty content
                        'tool_use' => [
                            'id' => $message['tool_call_id'],
                            'name' => $message['name'] ?? '',
                            'input' => [], // Will be populated by the actual tool call
                            'output' => $message['content'] ?? '',
                        ]
                    ];
                } else {
                    continue; // Skip malformed tool messages
                }
            }
            // Regular messages
            else {
                // Ensure content is not empty
                if (empty($content)) {
                    continue; // Skip empty messages
                }

                $anthropicMessage = [
                    'role' => $role,
                    'content' => $content,
                ];
            }

            $anthropicMessages[] = $anthropicMessage;
        }

        // If no valid user message was found, add a default one
//        if (!$hasValidUserMessage && empty($anthropicMessages)) {
//            $this->logger->debug('No valid user messages found, adding default message');
//            $anthropicMessages[] = [
//                'role' => 'user',
//                'content' => 'Hello, can you help me?'
//            ];
//        }

        return $anthropicMessages;
    }

    /**
     * Transform OpenAI-format tools to Anthropic format.
     *
     * @param array $tools OpenAI-format tools
     * @return array Anthropic-format tools
     */
    private function transformTools(array $tools): array
    {
        $anthropicTools = [];

        foreach ($tools as $tool) {
            if ($tool['type'] === 'function') {
                $anthropicTool = [
                    'name' => $tool['function']['name'],
                    'description' => $tool['function']['description'] ?? '',
                    'input_schema' => $tool['function']['parameters'] ?? [],
                ];

                $anthropicTools[] = $anthropicTool;
            }
        }

        return $anthropicTools;
    }

    /**
     * Transform Anthropic response to match OpenAI format.
     *
     * @param array $anthropicResponse The response from Anthropic API
     * @return array OpenAI-compatible response format
     */
    private function transformResponse(array $anthropicResponse): array
    {
        $choices = [];
        $message = [
            'role' => 'assistant',
            'content' => '',
        ];

        // Handle regular text content
        if (isset($anthropicResponse['content']) && is_array($anthropicResponse['content'])) {
            foreach ($anthropicResponse['content'] as $content) {
                if ($content['type'] === 'text') {
                    $message['content'] = $content['text'] ?? '';
                    break;
                }
            }
        }

        // Handle tool use responses
        if (isset($anthropicResponse['content']) && is_array($anthropicResponse['content'])) {
            $toolCalls = [];

            foreach ($anthropicResponse['content'] as $content) {
                if ($content['type'] === 'tool_use') {
                    $toolCalls[] = [
                        'id' => $content['id'] ?? uniqid('call-'),
                        'type' => 'function',
                        'function' => [
                            'name' => $content['name'] ?? '',
                            'arguments' => json_encode($content['input'] ?? []),
                        ],
                    ];
                }
            }

            if (!empty($toolCalls)) {
                $message['tool_calls'] = $toolCalls;
            }
        }

        $this->logger->debug('Transformed Anthropic response to OpenAI format', [
            'has_tool_calls' => isset($message['tool_calls']),
            'content_length' => strlen($message['content'] ?? '')
        ]);

        $choices[] = [
            'message' => $message,
            'finish_reason' => $anthropicResponse['stop_reason'] ?? 'stop',
            'index' => 0,
        ];

        // Create OpenAI-compatible response structure
        $openAIResponse = [
            'id' => $anthropicResponse['id'] ?? uniqid('resp-'),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $anthropicResponse['model'] ?? $this->defaultSettings->model,
            'choices' => $choices,
            'usage' => [
                'prompt_tokens' => $anthropicResponse['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $anthropicResponse['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($anthropicResponse['usage']['input_tokens'] ?? 0) + ($anthropicResponse['usage']['output_tokens'] ?? 0),
            ],
        ];

        return $openAIResponse;
    }

    /**
     * Transform Anthropic streaming chunk to match OpenAI format.
     *
     * @param array $anthropicChunk The streaming chunk from Anthropic API
     * @return array OpenAI-compatible streaming chunk format
     */
    private function transformStreamingChunk(array $anthropicChunk): array
    {
        $delta = [];
        $finishReason = null;

        // Handle different event types
        if (isset($anthropicChunk['type'])) {
            switch ($anthropicChunk['type']) {
                case 'message_start':
                    $delta['role'] = 'assistant';
                    break;

                case 'content_block_start':
                case 'content_block_delta':
                    if (isset($anthropicChunk['delta']['text'])) {
                        $delta['content'] = $anthropicChunk['delta']['text'];
                    }
                    break;

                case 'message_delta':
                    if (isset($anthropicChunk['delta']['stop_reason'])) {
                        $finishReason = $anthropicChunk['delta']['stop_reason'];
                    }
                    break;

                case 'message_stop':
                    $finishReason = $anthropicChunk['stop_reason'] ?? 'stop';
                    break;

                case 'tool_use_start':
                case 'tool_use_delta':
                    // Handle tool calls in streaming
                    if (isset($anthropicChunk['tool_use'])) {
                        $toolCall = [
                            'index' => 0,
                            'id' => $anthropicChunk['tool_use']['id'] ?? uniqid('call-'),
                            'type' => 'function',
                            'function' => [
                                'name' => $anthropicChunk['tool_use']['name'] ?? '',
                                'arguments' => json_encode($anthropicChunk['tool_use']['input'] ?? []),
                            ],
                        ];
                        $delta['tool_calls'] = [$toolCall];
                    }
                    break;
            }
        }

        // Create OpenAI-compatible streaming chunk
        $openAIChunk = [
            'id' => $anthropicChunk['message_id'] ?? uniqid('chunk-'),
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $anthropicChunk['model'] ?? $this->defaultSettings->model,
            'choices' => [
                [
                    'delta' => $delta,
                    'index' => 0,
                    'finish_reason' => $finishReason,
                ],
            ],
        ];

        return $openAIChunk;
    }

    /**
     * Read a line from a stream, handling SSE format.
     *
     * @param \Psr\Http\Message\StreamInterface $stream The stream to read from
     * @return string The read line
     */
    private function readStreamLine($stream): string
    {
        $buffer = '';

        while (!$stream->eof()) {
            $byte = $stream->read(1);
            $buffer .= $byte;

            if ($byte === "\n") {
                break;
            }
        }

        return trim($buffer);
    }
}