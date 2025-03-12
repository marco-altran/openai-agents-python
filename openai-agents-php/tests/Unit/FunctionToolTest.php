<?php

namespace OpenAI\Agents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenAI\Agents\FunctionTool;
use OpenAI\Agents\Tool;

class FunctionToolTest extends TestCase
{
    /**
     * Test weather function for tool tests
     */
    public function getWeather(string $city, ?string $units = 'celsius'): string
    {
        return "The weather in {$city} is sunny and " . ($units === 'fahrenheit' ? '72°F' : '22°C');
    }
    
    public function testFunctionToolImplementsTool()
    {
        $tool = new FunctionTool([$this, 'getWeather']);
        $this->assertInstanceOf(Tool::class, $tool);
    }
    
    public function testGetNameReturnsMethodName()
    {
        $tool = new FunctionTool([$this, 'getWeather']);
        $this->assertEquals('getWeather', $tool->getName());
    }
    
    public function testGetNameReturnsCustomName()
    {
        $tool = new FunctionTool([$this, 'getWeather'], 'weatherService');
        $this->assertEquals('weatherService', $tool->getName());
    }
    
    public function testGetDescriptionReturnsDocComment()
    {
        $tool = new FunctionTool([$this, 'getWeather']);
        $this->assertEquals('Test weather function for tool tests', $tool->getDescription());
    }
    
    public function testGetDescriptionReturnsCustomDescription()
    {
        $tool = new FunctionTool(
            [$this, 'getWeather'],
            description: 'Get current weather for a city'
        );
        $this->assertEquals('Get current weather for a city', $tool->getDescription());
    }
    
    public function testGetParametersReturnsSchemaWithRequiredParam()
    {
        $tool = new FunctionTool([$this, 'getWeather']);
        $params = $tool->getParameters();
        
        $this->assertEquals('object', $params['type']);
        $this->assertArrayHasKey('properties', $params);
        $this->assertArrayHasKey('city', $params['properties']);
        $this->assertArrayHasKey('units', $params['properties']);
        $this->assertEquals(['city'], $params['required']);
    }
    
    public function testExecuteCallsFunction()
    {
        $tool = new FunctionTool([$this, 'getWeather']);
        $result = $tool->execute(['city' => 'Tokyo', 'units' => 'fahrenheit']);
        
        $this->assertEquals('The weather in Tokyo is sunny and 72°F', $result);
    }
    
    public function testExecuteWithDefaultParams()
    {
        $tool = new FunctionTool([$this, 'getWeather']);
        $result = $tool->execute(['city' => 'Tokyo']);
        
        $this->assertEquals('The weather in Tokyo is sunny and 22°C', $result);
    }
    
    public function testClosureTool()
    {
        $closure = function(string $message): string {
            return "Echo: {$message}";
        };
        
        $tool = new FunctionTool($closure, 'echo', 'Echo back a message');
        
        $this->assertEquals('echo', $tool->getName());
        $this->assertEquals('Echo back a message', $tool->getDescription());
        $this->assertEquals('Echo: hello', $tool->execute(['message' => 'hello']));
    }
}