<?php

namespace OpenAI\Agents\Models;

use React\Promise\Promise;
use React\Promise\PromiseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use OpenAI\Agents\ModelSettings;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use InvalidArgumentException;

/**
 * OpenAI API implementation of the model interface.
 */
class OpenAIModel implements ModelInterface
{
    private Client $client;
    private string $apiKey;
    private ModelSettings $defaultSettings;
    private LoggerInterface $logger;
    private string $baseUrl;
    
    /**
     * Create a new OpenAI model.
     *
     * @param string $apiKey OpenAI API key
     * @param ModelSettings|null $defaultSettings Default model settings
     * @param LoggerInterface|null $logger Logger for API interactions
     * @param string $baseUrl Base URL for the OpenAI API
     */
    public function __construct(
        string $apiKey,
        ?ModelSettings $defaultSettings = null,
        ?LoggerInterface $logger = null,
        string $baseUrl = 'https://api.openai.com/v1'
    ) {
        if (empty($apiKey)) {
            throw new InvalidArgumentException('API key cannot be empty');
        }
        
        $this->apiKey = $apiKey;
        $this->defaultSettings = $defaultSettings ?? new ModelSettings(
            model: 'gpt-4-turbo-preview',
            temperature: 0.7
        );
        $this->logger = $logger ?? new NullLogger();
        $this->baseUrl = $baseUrl;
        
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
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
            $this->logger->debug('Sending request to OpenAI API', [
                'model' => $settings['model'],
                'message_count' => count($messages),
            ]);
            
            $response = $this->client->post('/chat/completions', [
                'json' => $payload,
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->debug('Received response from OpenAI API', [
                'status' => $response->getStatusCode(),
                'usage' => $responseData['usage'] ?? null,
            ]);
            
            return $responseData;
        } catch (\Exception $e) {
            $this->logger->error('Error calling OpenAI API', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function generateAsync(array $messages, array $options = []): PromiseInterface
    {
        $settings = $this->mergeSettings($options);
        $payload = $this->buildRequestPayload($messages, $settings);
        
        $this->logger->debug('Sending async request to OpenAI API', [
            'model' => $settings['model'],
            'message_count' => count($messages),
        ]);
        
        $guzzlePromise = $this->client->postAsync('/chat/completions', [
            'json' => $payload,
        ]);
        
        return new Promise(function ($resolve, $reject) use ($guzzlePromise) {
            $guzzlePromise->then(
                function ($response) use ($resolve) {
                    $responseData = json_decode($response->getBody()->getContents(), true);
                    
                    $this->logger->debug('Received async response from OpenAI API', [
                        'status' => $response->getStatusCode(),
                        'usage' => $responseData['usage'] ?? null,
                    ]);
                    
                    $resolve($responseData);
                },
                function ($reason) use ($reject) {
                    $this->logger->error('Error in async OpenAI API call', [
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
            $this->logger->debug('Starting stream request to OpenAI API', [
                'model' => $settings['model'],
                'message_count' => count($messages),
            ]);
            
            $response = $this->client->post('/chat/completions', [
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
                    
                    if ($data && isset($data['choices'][0])) {
                        yield $data;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in stream from OpenAI API', [
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
     * Build the request payload for the OpenAI API.
     *
     * @param array $messages Messages to send
     * @param array $settings Model settings
     * @return array The complete request payload
     */
    private function buildRequestPayload(array $messages, array $settings): array
    {
        $payload = [
            'messages' => $messages,
        ];
        
        // Add all settings to the payload
        foreach ($settings as $key => $value) {
            $payload[$key] = $value;
        }
        
        return $payload;
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