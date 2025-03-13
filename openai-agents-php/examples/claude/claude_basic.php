<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenAI\Agents\Agent;
use OpenAI\Agents\FunctionTool;
use OpenAI\Agents\ModelSettings;
use OpenAI\Agents\Runner;
use OpenAI\Agents\Models\AnthropicModel;

// Get API key from environment variable
$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    die("Error: ANTHROPIC_API_KEY environment variable is not set.\n");
}

/**
 * Get current weather information.
 *
 * @param string $location The location to get weather for
 * @return string Weather information
 */
function getCurrentWeather(string $location): string {
    // Simulate weather data
    $conditions = ["sunny", "partly cloudy", "cloudy", "rainy", "stormy", "snowy", "windy"];
    $temperatures = range(0, 40); // Celsius

    $condition = $conditions[array_rand($conditions)];
    $temperature = $temperatures[array_rand($temperatures)];

    return "Current weather in {$location}: {$condition} with a temperature of {$temperature}°C.";
}

// Create function tools
$weatherTool = new FunctionTool('getCurrentWeather', 'getCurrentWeather', 'Get current weather for a location');

// Create model settings for Claude
$modelSettings = new ModelSettings(
    model: 'claude-3-7-sonnet-latest',
    temperature: 0.7
);

// Create a custom Anthropic model
$anthropicModel = new AnthropicModel(
    apiKey: $apiKey,
    defaultSettings: $modelSettings
);

// Create our agent with Claude model
$agent = new Agent(
    name: "Weather Assistant",
    instructions: "You are a helpful weather assistant. When someone asks about the weather in a location, use the weather tool to get current conditions.",
    tools: [$weatherTool],
    modelSettings: $modelSettings
);

echo "==========================================================\n";
echo "Claude Basic Example - Weather Tool\n";
echo "==========================================================\n";

// Test question
$question = "What's the weather like in Paris?";
echo "Question: $question\n\n";

try {
    echo "Processing...\n";

    // Run the agent directly with anthropic model
    $result = Runner::runSync(
        $agent,
        $question,
        [
            'api_key' => $apiKey,
            'model' => $anthropicModel
        ]
    );

    // Print the result
    echo "\nResult:\n";
    echo "Final answer: " . $result->getFinalOutputAsString() . "\n\n";

    // Print messages
    echo "Message history:\n";
    foreach ($result->messages as $index => $message) {
        $role = $message['role'];
        $content = is_array($message['content']) ? json_encode($message['content']) : $message['content'];
        echo "[{$index}] {$role}: {$content}\n";
    }

    // Print steps
    echo "\nSteps:\n";
    foreach ($result->steps as $step) {
        echo "- " . $step['action'];
        if (isset($step['tool'])) {
            echo " (tool: " . $step['tool'] . ")";
        }
        echo "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n==========================================================\n";