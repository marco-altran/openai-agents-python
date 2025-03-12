<?php

namespace OpenAI\Agents;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * A tool implementation that wraps a PHP function or method.
 */
class FunctionTool implements Tool
{
    private string $name;
    private string $description;
    private array $parameters;
    private $callable;

    /**
     * Create a new function tool.
     *
     * @param callable $callable The callable to wrap
     * @param string|null $name Custom name for the tool (defaults to function name)
     * @param string|null $description Custom description for the tool
     */
    public function __construct(
        mixed $callable,
        ?string $name = null,
        ?string $description = null
    ) {
        // Verify callable
        if (!is_callable($callable)) {
            throw new \InvalidArgumentException('Argument must be a valid callable');
        }
        
        $this->callable = $callable;
        
        // Extract function metadata using reflection
        if (is_array($callable) && count($callable) === 2) {
            // Method call [object or class, method]
            $reflection = new ReflectionMethod($callable[0], $callable[1]);
            $this->name = $name ?? $reflection->getName();
        } else {
            // Function call
            $reflection = new ReflectionFunction($callable);
            $this->name = $name ?? $reflection->getName();
        }
        
        // Get PHPDoc comment if available
        $docComment = $reflection->getDocComment();
        $this->description = $description ?? $this->parseDocComment($docComment);
        
        // Generate parameters schema
        $this->parameters = $this->buildParametersSchema($reflection);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $parameters): mixed
    {
        return call_user_func($this->callable, ...$parameters);
    }
    
    /**
     * Parse PHPDoc comment to extract description.
     *
     * @param string|false $docComment The PHPDoc comment or false if none exists
     * @return string The extracted description or a default value
     */
    private function parseDocComment($docComment): string
    {
        if (!$docComment) {
            return 'No description available';
        }
        
        // Simple extraction of the first line after removing comment syntax
        $lines = explode("\n", $docComment);
        $description = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\s*\/\*\*|\s*\*\/|\s*\*\s*/', '', $line);
            $line = trim($line);
            
            if (!empty($line) && !str_starts_with($line, '@')) {
                $description = $line;
                break;
            }
        }
        
        return !empty($description) ? $description : 'No description available';
    }
    
    /**
     * Build JSON Schema for function parameters.
     *
     * @param ReflectionFunction|ReflectionMethod $reflection Function reflection
     * @return array The parameters schema
     */
    private function buildParametersSchema($reflection): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];
        
        foreach ($reflection->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $this->resolveParameterType($param);
            
            $schema['properties'][$paramName] = $paramType;
            
            // Mark parameter as required if it doesn't have a default value
            if (!$param->isOptional()) {
                $schema['required'][] = $paramName;
            }
        }
        
        return $schema;
    }
    
    /**
     * Resolve parameter type to JSON Schema type.
     *
     * @param ReflectionParameter $param Parameter reflection
     * @return array The JSON Schema type definition
     */
    private function resolveParameterType(ReflectionParameter $param): array
    {
        $typeInfo = ['type' => 'string', 'description' => ''];
        
        $type = $param->getType();
        
        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            $typeInfo = $this->mapPhpTypeToJsonSchema($typeName, $type->allowsNull());
        } elseif ($type instanceof ReflectionUnionType) {
            $types = $type->getTypes();
            $jsonSchemaTypes = [];
            $allowsNull = false;
            
            foreach ($types as $unionType) {
                if ($unionType->getName() === 'null') {
                    $allowsNull = true;
                    continue;
                }
                
                $jsonSchemaTypes[] = $this->mapPhpTypeToJsonSchema($unionType->getName(), false);
            }
            
            if (count($jsonSchemaTypes) === 1) {
                $typeInfo = $jsonSchemaTypes[0];
                if ($allowsNull) {
                    $typeInfo['nullable'] = true;
                }
            } else {
                // Handle union types by using oneOf in JSON Schema
                $typeInfo = [
                    'oneOf' => $jsonSchemaTypes
                ];
                
                if ($allowsNull) {
                    $typeInfo['nullable'] = true;
                }
            }
        }
        
        // Add default value if available
        if ($param->isDefaultValueAvailable()) {
            $typeInfo['default'] = $param->getDefaultValue();
        }
        
        return $typeInfo;
    }
    
    /**
     * Map PHP type to JSON Schema type.
     *
     * @param string $phpType PHP type name
     * @param bool $nullable Whether the type is nullable
     * @return array The JSON Schema type definition
     */
    private function mapPhpTypeToJsonSchema(string $phpType, bool $nullable): array
    {
        $typeMap = [
            'int' => ['type' => 'integer'],
            'float' => ['type' => 'number'],
            'string' => ['type' => 'string'],
            'bool' => ['type' => 'boolean'],
            'array' => ['type' => 'array', 'items' => ['type' => 'string']],
            'object' => ['type' => 'object'],
            // Add more type mappings as needed
        ];
        
        $result = $typeMap[$phpType] ?? ['type' => 'string'];
        
        if ($nullable) {
            $result['nullable'] = true;
        }
        
        return $result;
    }
}