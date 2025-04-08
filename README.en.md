# PHP MCP Server

[中文版本](README.md)

This is a PHP-based MCP (Model Control Protocol) server framework that supports elegant MCP service definition through annotations.

## Project Overview

This project provides a complete MCP server implementation with the following features:

- Annotation-based MCP service definition
- Support for Tool, Prompt, and Resource handlers
- Complete logging system
- Docker support

## System Requirements

- PHP >= 8.1
- Composer
- Docker (optional)
- Swow extension >= 1.5

## Quick Start

### Installation

```bash
# 1. Clone the project
git clone https://github.com/he426100/php-mcp-server
cd php-mcp-server

# 2. Install dependencies
composer install

# 3. Install Swow extension (if not installed)
./vendor/bin/swow-builder --install
```

> For detailed installation instructions for the Swow extension, please refer to the [Swow Official Documentation](https://github.com/swow/swow)

### Run Example Server

```bash
php bin/console mcp:test-server
```

## Annotation Usage Guide

This framework provides three core annotations for defining MCP services:

### 1. Tool Annotation

Used to define tool-type handlers:

```php
use Mcp\Annotation\Tool;

class MyService {
    #[Tool(
        name: 'calculate-sum',
        description: 'Calculate the sum of two numbers',
        parameters: [
            'num1' => [
                'type' => 'number',
                'description' => 'First number',
                'required' => true
            ],
            'num2' => [
                'type' => 'number',
                'description' => 'Second number',
                'required' => true
            ]
        ]
    )]
    public function sum(int $num1, int $num2): int 
    {
        return $num1 + $num2;
    }
}
```

### 2. Prompt Annotation

Used to define prompt template handlers:

```php
use Mcp\Annotation\Prompt;

class MyService {
    #[Prompt(
        name: 'greeting',
        description: 'Generate a greeting message',
        arguments: [
            'name' => [
                'description' => 'Name to greet',
                'required' => true
            ]
        ]
    )]
    public function greeting(string $name): string 
    {
        return "Hello, {$name}!";
    }
}
```

### 3. Resource Annotation

Used to define resource handlers:

```php
use Mcp\Annotation\Resource;

class MyService {
    #[Resource(
        uri: 'example://greeting',
        name: 'Greeting Text',
        description: 'Greeting resource',
        mimeType: 'text/plain'
    )]
    public function getGreeting(): string 
    {
        return "Hello from MCP server!";
    }
}
```

## Creating Custom Services

1. Create a service class:

```php
namespace Your\Namespace;

use Mcp\Annotation\Tool;
use Mcp\Annotation\Prompt;
use Mcp\Annotation\Resource;

class CustomService 
{
    #[Tool(name: 'custom-tool', description: 'Custom tool')]
    public function customTool(): string 
    {
        return "Custom tool result";
    }
}
```

2. Create a command class:

```php
namespace Your\Namespace\Command;

use He426100\McpServer\Command\AbstractMcpServerCommand;
use Your\Namespace\CustomService;

class CustomServerCommand extends AbstractMcpServerCommand 
{
    protected string $serverName = 'custom-server';
    protected string $serviceClass = CustomService::class;

    protected function configure(): void 
    {
        parent::configure();
        $this->setName('custom:server')
            ->setDescription('Run custom MCP server');
    }
}
```

## Annotation Parameters

### Tool Annotation Parameters

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| name | string | Tool name | Yes |
| description | string | Tool description | Yes |
| parameters | array | Parameter definitions | No |

### Prompt Annotation Parameters

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| name | string | Prompt template name | Yes |
| description | string | Prompt template description | Yes |
| arguments | array | Parameter definitions | No |

### Resource Annotation Parameters

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| uri | string | Resource URI | Yes |
| name | string | Resource name | Yes |
| description | string | Resource description | Yes |
| mimeType | string | MIME type | No |

## Annotation Function Return Types

### Tool Annotation Function Supported Return Types

| Return Type | Description | Conversion Result |
|-------------|-------------|-------------------|
| TextContent/ImageContent/EmbeddedResource | Direct content object | Preserved as-is |
| TextContent/ImageContent/EmbeddedResource array | Array of content objects | Preserved as-is |
| ResourceContents | Resource content object | Converted to EmbeddedResource |
| String or scalar type | string, int, float, bool | Converted to TextContent |
| null | Empty value | Converted to TextContent with empty string |
| Array or object | Complex data structure | Converted to TextContent with JSON format |

### Prompt Annotation Function Supported Return Types

| Return Type | Description | Conversion Result |
|-------------|-------------|-------------------|
| PromptMessage | Message object | Preserved as-is |
| PromptMessage array | Array of message objects | Preserved as-is |
| Content object | TextContent/ImageContent etc. | Converted to PromptMessage with user role |
| String or scalar type | string, int, float, bool | Converted to user message with TextContent |
| null | Empty value | Converted to user message with empty content |
| Array or object | Complex data structure | Converted to user message with JSON format |

### Resource Annotation Function Supported Return Types

| Return Type | Description | Conversion Result |
|-------------|-------------|-------------------|
| TextResourceContents/BlobResourceContents | Resource content object | Preserved as-is |
| ResourceContents array | Array of resource content objects | Preserved as-is |
| String or stringable object | Text content | Converted to appropriate resource content based on MIME type |
| null | Empty value | Converted to empty TextResourceContents |
| Array or object | Complex data structure | Converted to resource content with JSON format |

Notes:
- Content larger than 2MB will be automatically truncated
- MIME types of text/* will use TextResourceContents
- Other MIME types will use BlobResourceContents

## Logging Configuration

Server logs are saved in `runtime/server_log.txt` by default. This can be modified by extending `AbstractMcpServerCommand`:

```php
protected string $logFilePath = '/custom/path/to/log.txt';
```

## Docker Support

Build and run container:

```bash
docker build -t php-mcp-server .
docker run -i --rm php-mcp-server
```

## License

[MIT License](LICENSE)

## Contributing

Issues and Pull Requests are welcome.

## Authors

[he426100](https://github.com/he426100/)  
[logiscape](https://github.com/logiscape/mcp-sdk-php)

## Changelog

### v1.0.0
- Initial release
- Implemented basic MCP server functionality
- Added Docker support 