<?php

namespace OpenAI\Agents;

use OpenAI\Agents\Models\ModelInterface;
use OpenAI\Agents\Models\OpenAIModel;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Runner for executing agents.
 */
class Runner
{
    /**
     * Maximum number of turns in an agent conversation.
     */
    private const DEFAULT_MAX_TURNS = 10;
    
    /**
     * Run an agent asynchronously.
     *
     * @param Agent $agent The agent to run
     * @param string $input User input to the agent
     * @param array $options Options for running the agent
     * @return PromiseInterface Promise that resolves to a Result
     */
    public static function run(Agent $agent, string $input, array $options = []): PromiseInterface
    {
        $runner = new self();
        return $runner->runInternal($agent, $input, $options);
    }
    
    /**
     * Run an agent synchronously.
     *
     * @param Agent $agent The agent to run
     * @param string $input User input to the agent
     * @param array $options Options for running the agent
     * @return Result The result of running the agent
     */
    public static function runSync(Agent $agent, string $input, array $options = []): Result
    {
        // For error logging
        $logger = $options['logger'] ?? new NullLogger();
        
        // Get API key from options or environment
        $apiKey = $options['api_key'] ?? getenv('OPENAI_API_KEY');
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('OpenAI API key must be provided via OPENAI_API_KEY environment variable or in options');
        }
        
        $logger->debug('Starting synchronous agent run', [
            'agent' => $agent->name,
        ]);
        
        // Create a direct sync call to OpenAI with the current settings
        $model = $options['model'] ?? new OpenAIModel($apiKey, $agent->modelSettings, $logger);
        $maxTurns = $options['max_turns'] ?? self::DEFAULT_MAX_TURNS;
        
        // Initialize conversation
        $messages = [
            ['role' => 'system', 'content' => $agent->getSystemPrompt()],
            ['role' => 'user', 'content' => $input]
        ];
        $steps = [];
        $usage = [];
        $currentTurn = 0;
        
        // Start processing turns
        while ($currentTurn < $maxTurns) {
            $currentTurn++;
            
            $logger->debug('Processing turn', ['turn' => $currentTurn]);
            $steps[] = [
                'turn' => $currentTurn,
                'agent' => $agent,
                'action' => 'thinking'
            ];
            
            // Configure the model with tools if available
            $modelOptions = $agent->modelSettings->toArray();
            if (!empty($agent->tools)) {
                $modelOptions['tools'] = $agent->getToolsConfig();
                $modelOptions['tool_choice'] = 'auto';
            }
            
            // Generate response
            $response = $model->generate($messages, $modelOptions);
            
            // Extract message
            $assistantMessage = $response['choices'][0]['message'] ?? null;
            if (!$assistantMessage) {
                throw new \RuntimeException('No message in model response');
            }
            
            $messages[] = $assistantMessage;
            
            // Track token usage
            if (isset($response['usage'])) {
                $usage[] = $response['usage'];
            }
            
            // Process tool calls if any
            if (isset($assistantMessage['tool_calls']) && !empty($assistantMessage['tool_calls'])) {
                $toolCalls = $assistantMessage['tool_calls'];
                $toolResponses = [];
                
                foreach ($toolCalls as $call) {
                    if ($call['type'] !== 'function') {
                        $logger->warning('Unsupported tool call type', [
                            'type' => $call['type']
                        ]);
                        continue;
                    }
                    
                    $function = $call['function'];
                    $toolName = $function['name'];
                    $toolArgs = json_decode($function['arguments'], true);
                    
                    $tool = $agent->findTool($toolName);
                    
                    if (!$tool) {
                        $logger->warning('Tool not found', [
                            'tool' => $toolName
                        ]);
                        $toolResponses[] = [
                            'tool_call_id' => $call['id'],
                            'role' => 'tool',
                            'name' => $toolName,
                            'content' => "Error: Tool '$toolName' not found"
                        ];
                        continue;
                    }
                    
                    try {
                        $logger->info('Executing tool', [
                            'tool' => $toolName,
                            'args' => $toolArgs
                        ]);
                        
                        $steps[] = [
                            'turn' => $currentTurn,
                            'agent' => $agent,
                            'action' => 'tool_call',
                            'tool' => $toolName,
                            'args' => $toolArgs
                        ];
                        
                        $result = $tool->execute($toolArgs);
                        
                        $resultStr = is_string($result) ? $result : json_encode($result);
                        
                        $toolResponses[] = [
                            'tool_call_id' => $call['id'],
                            'role' => 'tool',
                            'name' => $toolName,
                            'content' => $resultStr
                        ];
                        
                        $logger->info('Tool execution successful', [
                            'tool' => $toolName
                        ]);
                    } catch (\Exception $e) {
                        $logger->error('Tool execution failed', [
                            'tool' => $toolName,
                            'error' => $e->getMessage()
                        ]);
                        
                        $toolResponses[] = [
                            'tool_call_id' => $call['id'],
                            'role' => 'tool',
                            'name' => $toolName,
                            'content' => "Error: {$e->getMessage()}"
                        ];
                    }
                }
                
                // Add tool responses to messages and continue to next turn
                $messages = array_merge($messages, $toolResponses);
                continue; // Go to next turn
            }
            
            // No tool calls, this is final output
            $finalOutput = $assistantMessage['content'];
            
            $steps[] = [
                'turn' => $currentTurn,
                'agent' => $agent,
                'action' => 'final_output',
                'output' => $finalOutput
            ];
            
            $logger->info('Agent completed with final output', [
                'agent' => $agent->name,
                'turns' => $currentTurn
            ]);
            
            return new Result(
                finalOutput: $finalOutput,
                messages: $messages,
                steps: $steps,
                usage: $usage
            );
        }
        
        // If we reach here, we hit the max turn limit
        $logger->warning('Reached maximum turns limit', [
            'max_turns' => $maxTurns,
            'agent' => $agent->name
        ]);
        
        return new Result(
            finalOutput: 'Maximum number of turns reached without final output',
            messages: $messages,
            steps: $steps,
            usage: $usage
        );
    }
    
    /**
     * Run an agent internally.
     *
     * @param Agent $agent The agent to run
     * @param string $input User input to the agent
     * @param array $options Options for running the agent
     * @return PromiseInterface Promise that resolves to a Result
     */
    private function runInternal(Agent $agent, string $input, array $options = []): PromiseInterface
    {
        $maxTurns = $options['max_turns'] ?? self::DEFAULT_MAX_TURNS;
        $apiKey = $options['api_key'] ?? getenv('OPENAI_API_KEY');
        $logger = $options['logger'] ?? new NullLogger();
        $model = $options['model'] ?? null;
        
        // Create model if not provided
        if ($model === null) {
            if (empty($apiKey)) {
                throw new \InvalidArgumentException('OpenAI API key must be provided via OPENAI_API_KEY environment variable or in options');
            }
            
            $model = new OpenAIModel($apiKey, $agent->modelSettings, $logger);
        }
        
        // Initialize conversation history
        $messages = [
            ['role' => 'system', 'content' => $agent->getSystemPrompt()],
            ['role' => 'user', 'content' => $input]
        ];
        
        // Initialize tracking variables
        $steps = [];
        $usage = [];
        $currentAgent = $agent;
        $currentTurn = 0;
        
        return new Promise(function ($resolve, $reject) use (
            &$messages, &$steps, &$usage, &$currentAgent, &$currentTurn,
            $maxTurns, $model, $logger
        ) {
            $this->runAgentTurn($currentAgent, $messages, $steps, $usage, $currentTurn, $maxTurns, $model, $logger)
                ->then(function (Result $result) use ($resolve) {
                    $resolve($result);
                })
                ->catch(function ($error) use ($reject) {
                    $reject($error);
                });
        });
    }
    
    /**
     * Run a single turn of the agent loop.
     *
     * @param Agent $agent Current agent
     * @param array $messages Message history
     * @param array $steps Execution steps
     * @param array $usage Token usage
     * @param int $currentTurn Current turn number
     * @param int $maxTurns Maximum allowed turns
     * @param ModelInterface $model The model to use
     * @param LoggerInterface $logger Logger
     * @return PromiseInterface Promise that resolves to a Result
     */
    private function runAgentTurn(
        Agent $agent,
        array &$messages,
        array &$steps,
        array &$usage,
        int &$currentTurn,
        int $maxTurns,
        ModelInterface $model,
        LoggerInterface $logger
    ): PromiseInterface {
        if ($currentTurn >= $maxTurns) {
            $logger->warning('Reached maximum turns limit', [
                'max_turns' => $maxTurns,
                'agent' => $agent->name
            ]);
            return new Promise(function ($resolve) use ($messages, $steps, $usage) {
                $resolve(new Result(
                    finalOutput: 'Maximum number of turns reached without final output',
                    messages: $messages,
                    steps: $steps,
                    usage: $usage
                ));
            });
        }
        
        $currentTurn++;
        $logger->debug('Starting agent turn', [
            'turn' => $currentTurn,
            'agent' => $agent->name
        ]);
        
        // Configure the model with tools if available
        $modelOptions = $agent->modelSettings->toArray();
        if (!empty($agent->tools)) {
            $modelOptions['tools'] = $agent->getToolsConfig();
            $modelOptions['tool_choice'] = 'auto';
        }
        
        // Add the step before we call the model
        $steps[] = [
            'turn' => $currentTurn,
            'agent' => $agent,
            'action' => 'thinking'
        ];
        
        return $model->generateAsync($messages, $modelOptions)
            ->then(function ($response) use (
                &$messages, &$steps, &$usage, &$currentTurn, $maxTurns,
                $agent, $model, $logger
            ) {
                // Extract the assistant message and add it to history
                $assistantMessage = $response['choices'][0]['message'] ?? null;
                if (!$assistantMessage) {
                    throw new \RuntimeException('No message in model response');
                }
                
                $messages[] = $assistantMessage;
                
                // Track token usage
                if (isset($response['usage'])) {
                    $usage[] = $response['usage'];
                }
                
                // Process tool calls if any
                if (isset($assistantMessage['tool_calls']) && !empty($assistantMessage['tool_calls'])) {
                    return $this->handleToolCalls(
                        $agent,
                        $assistantMessage['tool_calls'],
                        $messages,
                        $steps,
                        $usage,
                        $currentTurn,
                        $maxTurns,
                        $model,
                        $logger
                    );
                }
                
                // Check for handoffs
                // In a complete implementation, this would detect handoff intentions
                // and transfer control to another agent
                
                // No tool calls or handoffs, treating this as final output
                $finalOutput = $assistantMessage['content'];
                
                $steps[] = [
                    'turn' => $currentTurn,
                    'agent' => $agent,
                    'action' => 'final_output',
                    'output' => $finalOutput
                ];
                
                $logger->info('Agent completed with final output', [
                    'agent' => $agent->name,
                    'turns' => $currentTurn
                ]);
                
                return new Result(
                    finalOutput: $finalOutput,
                    messages: $messages,
                    steps: $steps,
                    usage: $usage
                );
            });
    }
    
    /**
     * Handle tool calls from the model.
     *
     * @param Agent $agent Current agent
     * @param array $toolCalls Tool calls from the model
     * @param array $messages Message history
     * @param array $steps Execution steps
     * @param array $usage Token usage
     * @param int $currentTurn Current turn number
     * @param int $maxTurns Maximum allowed turns
     * @param ModelInterface $model The model to use
     * @param LoggerInterface $logger Logger
     * @return PromiseInterface Promise that resolves to a Result
     */
    private function handleToolCalls(
        Agent $agent,
        array $toolCalls,
        array &$messages,
        array &$steps,
        array &$usage,
        int &$currentTurn,
        int $maxTurns,
        ModelInterface $model,
        LoggerInterface $logger
    ): PromiseInterface {
        $toolResponses = [];
        
        foreach ($toolCalls as $call) {
            if ($call['type'] !== 'function') {
                $logger->warning('Unsupported tool call type', [
                    'type' => $call['type']
                ]);
                continue;
            }
            
            $function = $call['function'];
            $toolName = $function['name'];
            $toolArgs = json_decode($function['arguments'], true);
            
            $tool = $agent->findTool($toolName);
            
            if (!$tool) {
                $logger->warning('Tool not found', [
                    'tool' => $toolName
                ]);
                $toolResponses[] = [
                    'tool_call_id' => $call['id'],
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => "Error: Tool '$toolName' not found"
                ];
                continue;
            }
            
            try {
                $logger->info('Executing tool', [
                    'tool' => $toolName,
                    'args' => $toolArgs
                ]);
                
                $steps[] = [
                    'turn' => $currentTurn,
                    'agent' => $agent,
                    'action' => 'tool_call',
                    'tool' => $toolName,
                    'args' => $toolArgs
                ];
                
                $result = $tool->execute($toolArgs);
                
                $resultStr = is_string($result) ? $result : json_encode($result);
                
                $toolResponses[] = [
                    'tool_call_id' => $call['id'],
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => $resultStr
                ];
                
                $logger->info('Tool execution successful', [
                    'tool' => $toolName
                ]);
            } catch (\Exception $e) {
                $logger->error('Tool execution failed', [
                    'tool' => $toolName,
                    'error' => $e->getMessage()
                ]);
                
                $toolResponses[] = [
                    'tool_call_id' => $call['id'],
                    'role' => 'tool',
                    'name' => $toolName,
                    'content' => "Error: {$e->getMessage()}"
                ];
            }
        }
        
        // Add tool responses to the message history
        $messages = array_merge($messages, $toolResponses);
        
        // Continue the agent loop
        return $this->runAgentTurn(
            $agent,
            $messages,
            $steps,
            $usage,
            $currentTurn,
            $maxTurns,
            $model,
            $logger
        );
    }
}