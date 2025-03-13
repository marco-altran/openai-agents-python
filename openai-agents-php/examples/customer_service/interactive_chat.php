<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenAI\Agents\Agent;
use OpenAI\Agents\FunctionTool;
use OpenAI\Agents\ModelSettings;
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
    } else if (strpos(strtolower($question), 'meal') !== false || strpos(strtolower($question), 'food') !== false) {
        return "We serve complimentary snacks and beverages on all flights. " .
               "Flights over 3 hours include a meal service for business class. " .
               "Special dietary meals can be requested 48 hours before departure.";
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

/**
 * Get flight status information.
 *
 * @param string $flight_number The flight number to check
 * @return string Flight status information
 */
function getFlightStatus(string $flight_number): string {
    // Simulate random flight statuses
    $statuses = [
        "On Time - Scheduled departure at 15:30",
        "Delayed by 25 minutes - New departure time 16:05",
        "Boarding - Gate closes in 20 minutes",
        "Now Boarding at Gate 12A"
    ];

    $randomStatus = $statuses[array_rand($statuses)];
    return "Flight {$flight_number} Status: {$randomStatus}";
}

// Create function tools with proper names and descriptions
$faqTool = new FunctionTool('faqLookupTool', 'lookupFAQ', 'Lookup frequently asked questions');
$seatTool = new FunctionTool('updateSeat', 'updateSeat', 'Update the seat for a given confirmation number');
$flightStatusTool = new FunctionTool('getFlightStatus', 'getFlightStatus', 'Get the current status of a flight');

// Create airline customer service agent
$customerServiceAgent = new Agent(
    name: "Airline Customer Service",
    instructions: "You are a helpful airline customer service agent. You help customers with their questions and requests in a friendly, professional manner.

# Capabilities
1. Answer frequently asked questions about baggage, seats, wifi, food, and other general information
2. Help customers update their seat assignments
3. Check flight status information

# FAQ Questions Routine
If the customer asks a question about baggage, seats, wifi, food, or other general information:
1. Identify the question topic
2. Use the lookupFAQ tool to find the answer
3. Provide the answer to the customer

# Seat Update Routine
If the customer wants to update their seat:
1. Ask for their confirmation number if they haven't provided it
2. Ask for their desired seat number if they haven't provided it
3. Use the updateSeat tool to update their seat
4. Confirm the change with the customer

# Flight Status Routine
If the customer asks about flight status:
1. Ask for the flight number if they haven't provided it
2. Use the getFlightStatus tool to check the status
3. Provide the status information to the customer

# Multi-turn Conversation Handling
The user input may include previous conversation history. When you see input that starts with 'Conversation so far:', read through that context to understand what has been discussed previously, then respond to the current message (after 'Current message:'). 

Maintain a helpful, professional tone at all times. Use previous conversation context to provide consistent responses and avoid asking for information that the customer has already provided.",
    tools: [$faqTool, $seatTool, $flightStatusTool],
    modelSettings: new ModelSettings(
        model: 'gpt-4o',
        temperature: 0.7
    )
);

// Initialize conversation memory
$conversationSummary = "";

// Print welcome message
echo "==========================================================\n";
echo "Interactive Airline Customer Service Chat\n";
echo "==========================================================\n";
echo "Welcome to our airline customer service! You can ask about:\n";
echo "- Baggage policies and restrictions\n";
echo "- Seat information or changing your seat\n";
echo "- Flight status (provide a flight number)\n";
echo "- WiFi availability\n";
echo "- Food and meal options\n";
echo "\nType 'exit' or 'quit' to end the conversation.\n\n";

// Start the interactive chat loop
while (true) {
    // Get user input
    echo "You: ";
    $userInput = trim(fgets(STDIN));

    // Check if user wants to exit
    if (strtolower($userInput) === 'exit' || strtolower($userInput) === 'quit') {
        echo "\nThank you for using our customer service chat. Goodbye!\n";
        break;
    }

    // Include conversation summary in the input if we have any previous context
    $contextualInput = $userInput;
    if (!empty($conversationSummary)) {
        $contextualInput = "Conversation so far:\n{$conversationSummary}\n\nCurrent message: {$userInput}";
    }

    try {
        // Run the agent with the contextual input
        $result = Runner::runSync(
            $customerServiceAgent,
            $contextualInput,
            ['api_key' => $apiKey]
        );

        // Get the agent's response
        $agentResponse = $result->getFinalOutputAsString();

        // Display the agent's response
        echo "Agent: {$agentResponse}\n\n";

        // Update conversation summary
        if (empty($conversationSummary)) {
            $conversationSummary = "User: {$userInput}\nAgent: {$agentResponse}";
        } else {
            $conversationSummary .= "\nUser: {$userInput}\nAgent: {$agentResponse}";
        }

    } catch (\Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}