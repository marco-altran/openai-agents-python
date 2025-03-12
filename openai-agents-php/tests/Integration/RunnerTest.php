<?php

namespace OpenAI\Agents\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OpenAI\Agents\Agent;
use OpenAI\Agents\Runner;
use OpenAI\Agents\FunctionTool;
use OpenAI\Agents\Tests\FakeModel;

class RunnerTest extends TestCase
{
    public function testRunSync()
    {
        // Create a fake model with a predefined response
        $fakeModel = new FakeModel([
            [
                'id' => 'chatcmpl-fake-id',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4-fake',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'Hello, I am an AI assistant.'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                    'total_tokens' => 18
                ]
            ]
        ]);
        
        // Create a simple agent
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent'
        );
        
        // Run the agent with the fake model
        $result = Runner::runSync($agent, 'Hello!', ['model' => $fakeModel]);
        
        // Verify the result
        $this->assertEquals('Hello, I am an AI assistant.', $result->finalOutput);
        $this->assertEquals(18, $result->getTotalUsage()['total_tokens']);
        $this->assertCount(3, $result->messages); // system + user + assistant
        $this->assertCount(2, $result->steps); // thinking + final_output
    }
    
    public function testRunWithTool()
    {
        // Create a function tool
        $weatherTool = new FunctionTool(
            function (string $city): string {
                return "The weather in {$city} is sunny.";
            },
            'getWeather',
            'Get weather information for a city'
        );
        
        // Create a fake model with a tool call response followed by a final response
        $fakeModel = new FakeModel([
            // First response - tool call
            [
                'id' => 'chatcmpl-fake-id-1',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4-fake',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => 'call_123',
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'getWeather',
                                        'arguments' => json_encode(['city' => 'Tokyo'])
                                    ]
                                ]
                            ]
                        ],
                        'finish_reason' => 'tool_calls'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 15,
                    'completion_tokens' => 10,
                    'total_tokens' => 25
                ]
            ],
            // Second response - final response
            [
                'id' => 'chatcmpl-fake-id-2',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => 'gpt-4-fake',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => 'The weather in Tokyo is sunny.'
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 25,
                    'completion_tokens' => 8,
                    'total_tokens' => 33
                ]
            ]
        ]);
        
        // Create an agent with the tool
        $agent = new Agent(
            name: 'WeatherAgent',
            instructions: 'You are a weather assistant',
            tools: [$weatherTool]
        );
        
        // Run the agent with the fake model
        $result = Runner::runSync($agent, 'What is the weather in Tokyo?', ['model' => $fakeModel]);
        
        // Verify the result
        $this->assertEquals('The weather in Tokyo is sunny.', $result->finalOutput);
        $this->assertEquals(58, $result->getTotalUsage()['total_tokens']); // 25 + 33
        $this->assertCount(5, $result->messages); // system + user + assistant(tool call) + tool + assistant(final)
        $this->assertCount(4, $result->steps); // thinking + tool call + thinking + final_output
    }
}