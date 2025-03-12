<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OpenAI\Agents\Agent;
use OpenAI\Agents\FunctionTool;
use OpenAI\Agents\ModelSettings;
use OpenAI\Agents\Runner;

// Define a simple tool to get the weather
function getWeather(string $city): string {
    return "The weather in {$city} is sunny and 72 degrees.";
}

// Create a function tool
$weatherTool = new FunctionTool(
    callable: 'getWeather',
    description: 'Get the current weather for a city'
);

// Create an agent with the tool
$agent = new Agent(
    name: "Weather Assistant",
    instructions: "You are a helpful assistant that can tell people the weather.",
    tools: [$weatherTool],
    modelSettings: new ModelSettings(
        model: 'gpt-4-turbo-preview',
        temperature: 0.7
    )
);

// User input
$userInput = "What's the weather like in Tokyo today?";

try {
    // Run the agent
    echo "Running agent with input: {$userInput}\n";
    $result = Runner::runSync($agent, $userInput);
    
    // Display the result
    echo "\nFinal output: " . $result->getFinalOutputAsString() . "\n";
    
    // Display token usage
    $usage = $result->getTotalUsage();
    echo "\nToken usage: {$usage['total_tokens']} total tokens ";
    echo "({$usage['prompt_tokens']} prompt, {$usage['completion_tokens']} completion)\n";
    
    // Print all steps
    echo "\nAgent steps:\n";
    foreach ($result->steps as $step) {
        echo "- Turn {$step['turn']}: {$step['action']}";
        if (isset($step['tool'])) {
            echo " (tool: {$step['tool']})";
        }
        echo "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}