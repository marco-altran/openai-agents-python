<?php

namespace OpenAI\Agents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenAI\Agents\Agent;
use OpenAI\Agents\Tool;
use OpenAI\Agents\ModelSettings;

class AgentTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent'
        );
        
        $this->assertEquals('TestAgent', $agent->name);
        $this->assertEquals('You are a test agent', $agent->instructions);
        $this->assertIsArray($agent->tools);
        $this->assertEmpty($agent->tools);
        $this->assertIsArray($agent->handoffs);
        $this->assertEmpty($agent->handoffs);
        $this->assertInstanceOf(ModelSettings::class, $agent->modelSettings);
    }
    
    public function testGetSystemPromptReturnsInstructions()
    {
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent'
        );
        
        $this->assertEquals('You are a test agent', $agent->getSystemPrompt());
    }
    
    public function testGetToolsConfigReturnsCorrectFormat()
    {
        // Create a mock tool
        $tool = $this->createMock(Tool::class);
        $tool->method('getName')->willReturn('testTool');
        $tool->method('getDescription')->willReturn('A test tool');
        $tool->method('getParameters')->willReturn([
            'type' => 'object',
            'properties' => [
                'param' => ['type' => 'string']
            ]
        ]);
        
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent',
            tools: [$tool]
        );
        
        $toolsConfig = $agent->getToolsConfig();
        
        $this->assertCount(1, $toolsConfig);
        $this->assertEquals('function', $toolsConfig[0]['type']);
        $this->assertEquals('testTool', $toolsConfig[0]['function']['name']);
        $this->assertEquals('A test tool', $toolsConfig[0]['function']['description']);
        $this->assertEquals([
            'type' => 'object',
            'properties' => [
                'param' => ['type' => 'string']
            ]
        ], $toolsConfig[0]['function']['parameters']);
    }
    
    public function testFindToolReturnsCorrectTool()
    {
        // Create mock tools
        $tool1 = $this->createMock(Tool::class);
        $tool1->method('getName')->willReturn('tool1');
        
        $tool2 = $this->createMock(Tool::class);
        $tool2->method('getName')->willReturn('tool2');
        
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent',
            tools: [$tool1, $tool2]
        );
        
        $this->assertSame($tool2, $agent->findTool('tool2'));
    }
    
    public function testFindToolReturnsNullForNonexistentTool()
    {
        $tool = $this->createMock(Tool::class);
        $tool->method('getName')->willReturn('tool1');
        
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent',
            tools: [$tool]
        );
        
        $this->assertNull($agent->findTool('nonexistent'));
    }
    
    public function testFindHandoffReturnsCorrectAgent()
    {
        $handoff1 = new Agent('HandoffAgent1', 'Handoff 1');
        $handoff2 = new Agent('HandoffAgent2', 'Handoff 2');
        
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent',
            handoffs: [$handoff1, $handoff2]
        );
        
        $this->assertSame($handoff2, $agent->findHandoff('HandoffAgent2'));
    }
    
    public function testFindHandoffReturnsNullForNonexistentAgent()
    {
        $handoff = new Agent('HandoffAgent', 'Handoff');
        
        $agent = new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent',
            handoffs: [$handoff]
        );
        
        $this->assertNull($agent->findHandoff('nonexistent'));
    }
    
    public function testAgentWithInvalidToolThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent',
            tools: ['not a tool']
        );
    }
    
    public function testAgentWithInvalidHandoffThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new Agent(
            name: 'TestAgent',
            instructions: 'You are a test agent',
            handoffs: ['not an agent']
        );
    }
}