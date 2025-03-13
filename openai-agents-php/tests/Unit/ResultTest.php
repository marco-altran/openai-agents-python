<?php

namespace OpenAI\Agents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenAI\Agents\Result;
use OpenAI\Agents\Agent;

class ResultTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $finalOutput = 'This is the final output';
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'This is the final output']
        ];
        $steps = [
            ['turn' => 1, 'action' => 'thinking'],
            ['turn' => 1, 'action' => 'final_output', 'output' => 'This is the final output']
        ];
        $usage = [
            ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15]
        ];
        
        $result = new Result($finalOutput, $messages, $steps, $usage);
        
        $this->assertEquals($finalOutput, $result->finalOutput);
        $this->assertEquals($messages, $result->messages);
        $this->assertEquals($steps, $result->steps);
        $this->assertEquals($usage, $result->usage);
    }
    
    public function testGetFinalOutputAsStringWithString()
    {
        $result = new Result('This is a string', []);
        $this->assertEquals('This is a string', $result->getFinalOutputAsString());
    }
    
    public function testGetFinalOutputAsStringWithArray()
    {
        $finalOutput = ['name' => 'John', 'age' => 30];
        $result = new Result($finalOutput, []);
        $expected = json_encode($finalOutput, JSON_PRETTY_PRINT);
        $this->assertEquals($expected, $result->getFinalOutputAsString());
    }
    
    public function testGetFinalOutputAsStringWithNonStringable()
    {
        $result = new Result(null, []);
        $this->assertEquals('', $result->getFinalOutputAsString());
    }
    
    public function testGetFinalAgentReturnsNullWithEmptySteps()
    {
        $result = new Result('Final output', [], []);
        $this->assertNull($result->getFinalAgent());
    }
    
    public function testGetFinalAgentReturnsLastAgent()
    {
        $agent = new Agent('TestAgent', 'Test instructions');
        
        $steps = [
            ['turn' => 1, 'agent' => $agent, 'action' => 'thinking'],
            ['turn' => 1, 'agent' => $agent, 'action' => 'final_output']
        ];
        
        $result = new Result('Final output', [], $steps);
        $this->assertSame($agent, $result->getFinalAgent());
    }
    
    public function testGetTotalUsageWithMultipleEntries()
    {
        $usage = [
            ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ['prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30]
        ];
        
        $result = new Result('Final output', [], [], $usage);
        $totalUsage = $result->getTotalUsage();
        
        $this->assertEquals(30, $totalUsage['prompt_tokens']);
        $this->assertEquals(15, $totalUsage['completion_tokens']);
        $this->assertEquals(45, $totalUsage['total_tokens']);
    }
    
    public function testGetTotalUsageWithEmptyUsage()
    {
        $result = new Result('Final output', [], [], []);
        $totalUsage = $result->getTotalUsage();
        
        $this->assertEquals(0, $totalUsage['prompt_tokens']);
        $this->assertEquals(0, $totalUsage['completion_tokens']);
        $this->assertEquals(0, $totalUsage['total_tokens']);
    }
    
    public function testGetTotalUsageWithMissingFields()
    {
        $usage = [
            ['prompt_tokens' => 10], // Missing completion_tokens and total_tokens
            ['completion_tokens' => 10] // Missing prompt_tokens and total_tokens
        ];
        
        $result = new Result('Final output', [], [], $usage);
        $totalUsage = $result->getTotalUsage();
        
        $this->assertEquals(10, $totalUsage['prompt_tokens']);
        $this->assertEquals(10, $totalUsage['completion_tokens']);
        $this->assertEquals(0, $totalUsage['total_tokens']); // No total_tokens in usage
    }
}