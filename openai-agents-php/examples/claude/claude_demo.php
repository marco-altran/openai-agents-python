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

// Define some useful tools
function searchDatabase(string $query): string {
    // Simplified database simulation
    $data = [
        'products' => [
            ['id' => 1, 'name' => 'Laptop', 'price' => 999.99, 'category' => 'Electronics'],
            ['id' => 2, 'name' => 'Headphones', 'price' => 199.99, 'category' => 'Audio'],
            ['id' => 3, 'name' => 'Phone', 'price' => 799.99, 'category' => 'Electronics'],
            ['id' => 4, 'name' => 'Monitor', 'price' => 349.99, 'category' => 'Electronics'],
            ['id' => 5, 'name' => 'Keyboard', 'price' => 129.99, 'category' => 'Accessories'],
        ]
    ];

    // Simple search simulation
    $results = [];
    foreach ($data['products'] as $product) {
        if (stripos($product['name'], $query) !== false ||
            stripos($product['category'], $query) !== false) {
            $results[] = $product;
        }
    }

    if (empty($results)) {
        return "No results found for query: {$query}";
    }

    return "Search results for '{$query}':\n" . json_encode($results, JSON_PRETTY_PRINT);
}

function calculateTax(float $amount, string $state = 'CA'): string {
    $taxRates = [
        'CA' => 0.0725,
        'NY' => 0.0845,
        'TX' => 0.0625,
        'FL' => 0.060,
        'WA' => 0.065,
    ];

    $rate = $taxRates[$state] ?? 0.05; // Default rate
    $tax = round($amount * $rate, 2);
    $total = round($amount + $tax, 2);

    return "Amount: \${$amount}\nTax rate ({$state}): " . ($rate * 100) . "%\nTax: \${$tax}\nTotal: \${$total}";
}

// Create function tools
$searchTool = new FunctionTool('searchDatabase', 'searchDatabase', 'Search the product database for items');
$taxTool = new FunctionTool('calculateTax', 'calculateTax', 'Calculate sales tax for a given amount and state');

// Available Claude models:
// - claude-3-5-sonnet-20240620
// - claude-3-haiku-20240307
// - claude-3-opus-20240229
// - claude-3-sonnet-20240229
// - claude-3-7-sonnet-latest (newest model)

// Create model settings for Claude
$modelSettings = new ModelSettings(
    model: 'claude-3-7-sonnet-latest', // You can change to other Claude models
    temperature: 0.7
);

// Create a custom Anthropic model
$anthropicModel = new AnthropicModel(
    apiKey: $apiKey,
    defaultSettings: $modelSettings
);

// Create our agent with Claude model
$assistant = new Agent(
    name: "Claude Assistant",
    instructions: "You are a helpful shopping assistant powered by Anthropic's Claude. You help users with product searches and tax calculations.

When helping users:
1. Be concise and accurate
2. Use the searchDatabase tool to look up products
3. Use the calculateTax tool to compute sales tax
4. Maintain a helpful, professional tone",
    tools: [$searchTool, $taxTool],
    modelSettings: $modelSettings
);

echo "==========================================================\n";
echo "Claude Demo - Shopping Assistant with Tools\n";
echo "==========================================================\n";
echo "Type your questions or requests below. Type 'exit' or 'quit' to end the conversation.\n\n";

// Initialize conversation memory
$conversationSummary = "";

// Start the interactive chat loop
while (true) {
    // Get user input
    echo "You: ";
    $userInput = trim(fgets(STDIN));

    // Check if user wants to exit
    if (strtolower($userInput) === 'exit' || strtolower($userInput) === 'quit') {
        echo "\nThank you for using the Claude Assistant. Goodbye!\n";
        break;
    }

    // Include conversation history in the input if we have any previous context
    $contextualInput = $userInput;
    if (!empty($conversationSummary)) {
        $contextualInput = "Conversation so far:\n{$conversationSummary}\n\nCurrent message: {$userInput}";
    }

    try {
        echo "Claude is thinking...\n";

        // Run the agent with the contextual input
        $result = Runner::runSync(
            $assistant,
            $contextualInput,
            [
                'api_key' => $apiKey,
                'model' => $anthropicModel // Pass the Anthropic model to the runner
            ]
        );

        // Get the agent's response
        $agentResponse = $result->getFinalOutputAsString();

        // Display the agent's response
        echo "Claude: {$agentResponse}\n\n";

        // Update conversation summary
        if (empty($conversationSummary)) {
            $conversationSummary = "User: {$userInput}\nClaude: {$agentResponse}";
        } else {
            $conversationSummary .= "\nUser: {$userInput}\nClaude: {$agentResponse}";
        }

    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}