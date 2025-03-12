# OpenAI Agents SDK - PHP Port Design Document

## Overview
This document outlines the strategy and architecture for porting the OpenAI Agents Python SDK to PHP 8.1+. The port aims to maintain feature parity while embracing PHP idiomatic patterns and best practices.

## Architecture Goals
- Maintain the core functionality of the Python SDK
- Embrace PHP idioms and design patterns
- Support PHP 8.1+ (leveraging attributes, named arguments, union types)
- Maintain consistent API with Python version where sensible
- Provide robust error handling and type safety

## PHP Dependencies & Technologies
- **JSON Schema Validation**: Use `opis/json-schema` or `justinrainbow/json-schema`
- **HTTP Client**: `guzzlehttp/guzzle` for API interactions
- **Async Support**: `react/promise` for Promise-based async operations or PHP 8.1 Fibers
- **Testing**: PHPUnit for testing framework
- **Type Checking**: PHPStan for static analysis
- **Documentation**: PHPDoc with OpenAPI annotations
- **Package Management**: Composer

## Core Components

### Agent
```php
class Agent {
    public function __construct(
        public string $name,
        public string $instructions,
        public ?array $tools = null,
        public ?array $handoffs = null,
        public ?array $guardrails = null,
        public ?string $outputType = null,
        public ?ModelSettings $modelSettings = null
    ) {}
}
```

### Runner
```php
class Runner {
    public static function run(Agent $agent, string $input, array $options = []): Promise;
    public static function runSync(Agent $agent, string $input, array $options = []): Result;
}
```

### Tool Interface
```php
interface Tool {
    public function getName(): string;
    public function getDescription(): string;
    public function getParameters(): array;
    public function execute(array $parameters): mixed;
}
```

### Handoff System
```php
class Handoff {
    public function __construct(
        public Agent $agent,
        public ?callable $inputFilter = null,
        public ?callable $outputFilter = null
    ) {}
}
```

## Porting Strategy

### Phase 1: Foundation
1. Set up project structure and core interfaces
2. Implement basic Agent and ModelSettings classes
3. Create OpenAI API client wrapper
4. Implement basic synchronous Runner

### Phase 2: Tools and Function Schema
1. Build JSON Schema generation for PHP functions
2. Implement Tool interface and FunctionTool implementation
3. Create tooling for decorated PHP methods

### Phase 3: Handoffs and Guardrails
1. Implement Handoff mechanism
2. Create input/output Guardrail system
3. Add filters for conversation history

### Phase 4: Async Support
1. Implement Promise-based Runner
2. Add support for async tool execution
3. Support streamed responses

### Phase 5: Testing and Examples
1. Create FakeModel for testing
2. Port example code
3. Write comprehensive tests
4. Create documentation

## Challenges and Solutions

### Asynchronous Programming
**Challenge**: PHP lacks native async/await syntax  
**Solution**: Use React/Promise or PHP 8.1 Fibers for cooperative multitasking

### Type System
**Challenge**: PHP's type system differs from Python  
**Solution**: Leverage PHP 8.1+ union types, nullables, and attributes

### Schema Validation
**Challenge**: No direct Pydantic equivalent  
**Solution**: Create schema validation layer with JSON Schema libraries

### Function Reflection
**Challenge**: Annotating and reflecting on function parameters  
**Solution**: Use PHP 8 attributes and ReflectionFunction

## Testing Strategy
- Create PHPUnit test suite paralleling Python tests
- Implement FakeModel for repeatable test scenarios
- Test async operations with PHPUnit's async testing
- Verify API compatibility with integration tests

## Project Structure
```
openai-agents-php/
├── src/
│   ├── Agent.php
│   ├── Runner.php
│   ├── Tool.php
│   ├── Handoff.php
│   ├── Guardrail.php
│   ├── Models/
│   │   ├── ModelInterface.php
│   │   ├── OpenAIModel.php
│   │   └── FakeModel.php
│   ├── Schema/
│   │   └── FunctionSchema.php
│   └── Tracing/
│       └── TraceProcessor.php
├── tests/
├── examples/
└── composer.json
```

## Performance Considerations
- Minimize memory usage in long-running processes
- Cache schema generation results
- Optimize HTTP connection pooling
- Consider lazy-loading for heavy components

## Timeline Estimation
- Phase 1: 2-3 weeks
- Phase 2: 2 weeks
- Phase 3: 2 weeks
- Phase 4: 1-2 weeks
- Phase 5: 2-3 weeks

Total: Approximately 9-12 weeks for full port