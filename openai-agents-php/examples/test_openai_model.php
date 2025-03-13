<?php

require_once __DIR__ . '/vendor/autoload.php';

use OpenAI\Agents\Models\OpenAIModel;
use OpenAI\Agents\ModelSettings;

// Create a simple logger that outputs to console
$logger = new class extends \Psr\Log\AbstractLogger {
    public function log($level, string|\Stringable $message, array $context = []): void {
        echo "[$level] $message" . PHP_EOL;
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . PHP_EOL;
        }
    }
};

// Get API key from environment variable
$apiKey = getenv('OPENAI_API_KEY');
if (!$apiKey) {
    die("Error: OPENAI_API_KEY environment variable is not set.\n");
}

// Create model settings
$modelSettings = new ModelSettings(
    model: 'gpt-4o',
    temperature: 0.7
);

// Initialize the OpenAI model
$model = new OpenAIModel(
    apiKey: $apiKey,
    defaultSettings: $modelSettings,
    logger: $logger
);

// Test messages
$messages = [
    [
        'role' => 'system',
        'content' => 'You are a helpful assistant. Keep your responses short and concise.'
    ],
    [
        'role' => 'user',
        'content' => 'Hello! What can you do?'
    ]
];

try {
    // Test synchronous generation
    echo "Testing synchronous generation...\n";
    $response = $model->generate($messages);
    echo "Response: " . $response['choices'][0]['message']['content'] . "\n\n";

    // Test streaming generation
    echo "Testing streaming generation...\n";
    $stream = $model->generateStream($messages);
    echo "Stream chunks: \n";
    $fullContent = '';
    foreach ($stream as $chunk) {
        $content = $chunk['choices'][0]['delta']['content'] ?? '';
        $fullContent .= $content;
        echo $content;
        flush();  // Flush output buffer to see streaming in real-time
    }
    echo "\n\nFull streamed content: $fullContent\n";

    echo "\nAll tests completed successfully!\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}