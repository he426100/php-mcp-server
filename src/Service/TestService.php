<?php

declare(strict_types=1);

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;
use Mcp\Annotation\Prompt;
use Mcp\Annotation\Resource;

class TestService extends BaseService
{
    #[Tool(name: 'sum', description: '计算两个数的和')]
    public function sum(int $num1, int $num2 = 0): int
    {
        return $num1 + $num2;
    }

    #[Prompt(
        name: 'greeting',
        description: '生成问候语',
        arguments: [
            'name' => ['description' => '要问候的人名', 'required' => true]
        ]
    )]
    public function greeting(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Resource(
        uri: 'example://greeting',
        name: 'Greeting Text',
        description: 'A simple greeting message'
    )]
    public function getGreeting(): string
    {
        return "Hello from the example MCP server!";
    }
}
