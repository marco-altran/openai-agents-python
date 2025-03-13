<?php

namespace OpenAI\Agents\Tests\Unit;

use PHPUnit\Framework\TestCase;
use OpenAI\Agents\ModelSettings;

class ModelSettingsTest extends TestCase
{
    public function testConstructorWithDefaults()
    {
        $settings = new ModelSettings();
        
        $this->assertNull($settings->model);
        $this->assertNull($settings->temperature);
        $this->assertNull($settings->maxTokens);
        $this->assertNull($settings->additionalArgs);
    }
    
    public function testConstructorWithValues()
    {
        $settings = new ModelSettings(
            model: 'gpt-4',
            temperature: 0.5,
            maxTokens: 1000,
            additionalArgs: ['top_p' => 0.9]
        );
        
        $this->assertEquals('gpt-4', $settings->model);
        $this->assertEquals(0.5, $settings->temperature);
        $this->assertEquals(1000, $settings->maxTokens);
        $this->assertEquals(['top_p' => 0.9], $settings->additionalArgs);
    }
    
    public function testToArray()
    {
        $settings = new ModelSettings(
            model: 'gpt-4',
            temperature: 0.5,
            maxTokens: 1000,
            additionalArgs: ['top_p' => 0.9]
        );
        
        $expected = [
            'model' => 'gpt-4',
            'temperature' => 0.5,
            'max_tokens' => 1000,
            'top_p' => 0.9
        ];
        
        $this->assertEquals($expected, $settings->toArray());
    }
    
    public function testToArrayWithNullValues()
    {
        $settings = new ModelSettings(
            model: 'gpt-4'
        );
        
        $expected = [
            'model' => 'gpt-4'
        ];
        
        $this->assertEquals($expected, $settings->toArray());
    }
    
    public function testInvalidTemperatureThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Temperature must be between 0 and 2');
        
        new ModelSettings(temperature: 3.0);
    }
    
    public function testInvalidMaxTokensThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max tokens must be greater than 0');
        
        new ModelSettings(maxTokens: -10);
    }
}