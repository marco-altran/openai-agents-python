<?php

namespace OpenAI\Agents\Tests;

use OpenAI\Agents\Models\ModelInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * Fake model implementation for testing.
 */
class FakeModel implements ModelInterface
{
    private array $responses;
    
    /**
     * Create a new fake model with predefined responses.
     *
     * @param array $responses Responses to return for each call
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
        
        // Default response if none provided
        if (empty($this->responses)) {
            $this->responses[] = [
                'id' => 'chatcmpl-fake-id',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4-fake',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'This is a fake response'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 10,
                    'total_tokens' => 20
                ]
            ];
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function generate(array $messages, array $options = []): array
    {
        // Return the first response and rotate responses for next call
        $response = $this->responses[0];
        $this->responses = array_merge(array_slice($this->responses, 1), [$response]);
        
        return $response;
    }
    
    /**
     * {@inheritdoc}
     */
    public function generateAsync(array $messages, array $options = []): PromiseInterface
    {
        return new Promise(function ($resolve) use ($messages, $options) {
            $resolve($this->generate($messages, $options));
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function generateStream(array $messages, array $options = []): \Generator
    {
        $response = $this->generate($messages, $options);
        
        // Split the response content into chunks for streaming
        $content = $response['choices'][0]['message']['content'];
        $chunks = str_split($content, 5);
        
        foreach ($chunks as $index => $chunk) {
            $isLast = $index === count($chunks) - 1;
            
            yield [
                'id' => 'chatcmpl-fake-id',
                'object' => 'chat.completion.chunk',
                'created' => time(),
                'model' => 'gpt-4-fake',
                'choices' => [
                    [
                        'index' => 0,
                        'delta' => [
                            'content' => $chunk
                        ],
                        'finish_reason' => $isLast ? 'stop' : null
                    ]
                ]
            ];
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function generateStreamAsync(array $messages, array $options = []): PromiseInterface
    {
        return new Promise(function ($resolve) use ($messages, $options) {
            $resolve($this->generateStream($messages, $options));
        });
    }
}