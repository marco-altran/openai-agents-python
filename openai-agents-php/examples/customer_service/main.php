<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenAI\Agents\Agent;
use OpenAI\Agents\FunctionTool;
use OpenAI\Agents\ModelSettings;
use OpenAI\Agents\Result;
use OpenAI\Agents\Runner;

// Get API key from environment variable
$apiKey = getenv('OPENAI_API_KEY');
if (!$apiKey) {
    die("Error: OPENAI_API_KEY environment variable is not set.\n");
}

/**
 * Lookup frequently asked questions.
 * 
 * @param string $question The customer's question
 * @return string The answer to the question
 */
function faqLookupTool(string $question): string {
    if (strpos(strtolower($question), 'bag') !== false || strpos(strtolower($question), 'baggage') !== false) {
        return "You are allowed to bring one bag on the plane. " .
               "It must be under 50 pounds and 22 inches x 14 inches x 9 inches.";
    } else if (strpos(strtolower($question), 'seat') !== false || strpos(strtolower($question), 'plane') !== false) {
        return "There are 120 seats on the plane. " .
               "There are 22 business class seats and 98 economy seats. " .
               "Exit rows are rows 4 and 16. " .
               "Rows 5-8 are Economy Plus, with extra legroom.";
    } else if (strpos(strtolower($question), 'wifi') !== false) {
        return "We have free wifi on the plane, join Airline-Wifi";
    }
    return "I'm sorry, I don't know the answer to that question.";
}

// Flight information globals
$flightNumber = 'FLT-' . rand(100, 999);
$passengerInfo = [
    'name' => null,
    'confirmation' => null,
    'seat' => null
];

/**
 * Update the seat for a given confirmation number.
 * 
 * @param string $confirmation_number The confirmation number for the flight
 * @param string $new_seat The new seat to update to
 * @return string Result message
 */
function updateSeat(string $confirmation_number, string $new_seat): string {
    global $flightNumber, $passengerInfo;
    
    $passengerInfo['confirmation'] = $confirmation_number;
    $passengerInfo['seat'] = $new_seat;
    
    return "Updated seat to {$new_seat} for confirmation number {$confirmation_number} on flight {$flightNumber}";
}

// Create function tools with proper names and descriptions
$faqTool = new FunctionTool('faqLookupTool', 'lookupFAQ', 'Lookup frequently asked questions');
$seatTool = new FunctionTool('updateSeat', 'updateSeat', 'Update the seat for a given confirmation number');

// Create airline customer service agent
$customerServiceAgent = new Agent(
    name: "Airline Customer Service",
    instructions: "You are a helpful airline customer service agent. You can answer FAQ questions and help customers update their seat assignments.

# Capabilities
1. Answer frequently asked questions about baggage, seats, wifi, etc.
2. Help customers update their seat assignments
    
# FAQ Questions Routine
If the customer asks a question about baggage, seats, wifi, or other general information:
1. Identify the question topic
2. Use the lookupFAQ tool to find the answer
3. Provide the answer to the customer

# Seat Update Routine
If the customer wants to update their seat:
1. Ask for their confirmation number if they haven't provided it
2. Ask for their desired seat number if they haven't provided it
3. Use the updateSeat tool to update their seat
4. Confirm the change with the customer

Maintain a helpful, professional tone at all times.",
    tools: [$faqTool, $seatTool],
    modelSettings: new ModelSettings(
        model: 'gpt-3.5-turbo',
        temperature: 0.7
    )
);

// Simple single-turn example
echo "==========================================================\n";
echo "Airline Customer Service Example\n";
echo "==========================================================\n\n";

$questions = [
    "What are the baggage restrictions?",
    "I need to change my seat. My confirmation number is ABC123 and I want seat 16C."
];

foreach ($questions as $index => $question) {
    echo "Question " . ($index + 1) . ": " . $question . "\n\n";
    
    try {
        echo "Processing...\n";
        $result = Runner::runSync(
            $customerServiceAgent, 
            $question,
            ['api_key' => $apiKey]
        );
        
        // Display the result
        echo "Agent response: " . $result->getFinalOutputAsString() . "\n";
        
        // Print steps information
        echo "\nSteps:\n";
        foreach ($result->steps as $step) {
            echo "- " . $step['action'];
            if (isset($step['tool'])) {
                echo " (tool: " . $step['tool'] . ")";
            }
            echo "\n";
        }
        
        echo "\n==========================================================\n\n";
        
    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}