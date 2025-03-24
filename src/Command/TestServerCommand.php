<?php

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\LoggerService;

use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use Mcp\Types\Tool;
use Mcp\Types\CallToolResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\TextContent;
use Mcp\Types\CallToolRequestParams;
use Mcp\Types\Content;
use Mcp\Types\ToolInputSchema;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\Resource;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\ResourceTemplate;
use Mcp\Types\ListResourceTemplatesResult;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\Prompt;
use Mcp\Types\PromptArgument;
use Mcp\Types\GetPromptRequestParams;
use Mcp\Types\GetPromptResult;
use Mcp\Types\PromptMessage;
use Mcp\Types\Role;
use Mcp\Types\TextResourceContents;

class TestServerCommand extends Command
{
    // 配置命令
    protected function configure(): void
    {
        $this->setName('mcp:test-server')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen on for SSE', 8000)
            ->addOption('transport', null, InputOption::VALUE_OPTIONAL, 'Transport type', 'stdin')
            ->setDescription('运行MCP测试服务器')
            ->setHelp('此命令启动一个MCP测试服务器');
    }

    // 执行命令
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = $input->getOption('transport');
        if (!in_array($transport, ['stdio', 'sse'])) {
            throw new \Exception('Unsupported transport: ' . $transport);
        }

        $port = $input->getOption('port');

        // 创建日志记录器
        $logger = LoggerService::createLogger(
            'php-mcp-server',
            BASE_PATH . '/runtime/server_log.txt',
            false
        );

        // Create a server instance
        $server = new Server('mcp-test-server');

        // Register prompt handlers (keeping existing code)
        $server->registerHandler('prompts/list', function ($params) {
            $prompt = new Prompt(
                name: 'example-prompt',
                description: 'An example prompt template',
                arguments: [
                    new PromptArgument(
                        name: 'arg1',
                        description: 'Example argument',
                        required: true
                    )
                ]
            );
            return new ListPromptsResult([$prompt]);
        });

        $server->registerHandler('prompts/get', function (GetPromptRequestParams $params) {
            $name = $params->name;
            $arguments = $params->arguments;
            if ($name !== 'example-prompt') {
                throw new \InvalidArgumentException("Unknown prompt: {$name}");
            }
            // Get argument value safely
            $argValue = $arguments ? $arguments->arg1 : 'none';
            $prompt = new Prompt(
                name: 'example-prompt',
                description: 'An example prompt template',
                arguments: [
                    new PromptArgument(
                        name: 'arg1',
                        description: 'Example argument',
                        required: true
                    )
                ]
            );
            return new GetPromptResult(
                messages: [
                    new PromptMessage(
                        role: Role::USER,
                        content: new TextContent(
                            text: "Example prompt text with argument: $argValue"
                        )
                    )
                ],
                description: 'Example prompt'
            );
        });

        // Add tool handlers
        $server->registerHandler('tools/list', function ($params) {
            // Create properties object first
            $properties = ToolInputProperties::fromArray([
                'num1' => [
                    'type' => 'number',
                    'description' => 'First number'
                ],
                'num2' => [
                    'type' => 'number',
                    'description' => 'Second number'
                ]
            ]);

            // Create schema with properties and required fields
            $inputSchema = new ToolInputSchema(
                properties: $properties,
                required: ['num1', 'num2']
            );

            $tool = new Tool(
                name: 'add-numbers',
                description: 'Adds two numbers together',
                inputSchema: $inputSchema
            );

            return new ListToolsResult([$tool]);
        });

        $server->registerHandler('tools/call', function ($params) {
            $name = $params->name;
            $arguments = $params->arguments ?? [];

            if ($name !== 'add-numbers') {
                throw new \InvalidArgumentException("Unknown tool: {$name}");
            }

            // Validate and convert arguments to numbers
            $num1 = filter_var($arguments['num1'] ?? null, FILTER_VALIDATE_FLOAT);
            $num2 = filter_var($arguments['num2'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($num1 === false || $num2 === false) {
                return new CallToolResult(
                    content: [new TextContent(
                        text: "Error: Both arguments must be valid numbers"
                    )],
                    isError: true
                );
            }

            $sum = $num1 + $num2;
            return new CallToolResult(
                content: [new TextContent(
                    text: "The sum of {$num1} and {$num2} is {$sum}"
                )]
            );
        });

        // Add resource handlers
        $server->registerHandler('resources/list', function ($params) {
            $resource = new Resource(
                uri: 'example://greeting',
                name: 'Greeting Text',
                description: 'A simple greeting message',
                mimeType: 'text/plain'
            );
            return new ListResourcesResult([$resource]);
        });

        $server->registerHandler('resources/read', function ($params) {
            $uri = $params->uri;
            if ($uri !== 'example://greeting') {
                throw new \InvalidArgumentException("Unknown resource: {$uri}");
            }

            return new ReadResourceResult(
                contents: [new TextResourceContents(
                    uri: $uri,
                    text: "Hello from the example MCP server!",
                    mimeType: 'text/plain'
                )]
            );
        });

        // 创建初始化选项并运行服务器
        $initOptions = $server->createInitializationOptions();
        $runner = new ServerRunner($logger, $transport, '0.0.0.0', $port);

        try {
            $runner->run($server, $initOptions);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $logger->error("服务器运行失败", ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
