<?php

declare(strict_types=1);

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\LoggerService;
use He426100\McpServer\Service\TestService;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;

class TestServerCommand extends AbstractMcpServerCommand
{
    protected string $serverName = 'mcp-test-server';
    protected string $serviceClass = TestService::class;

    // 配置命令
    protected function configure(): void
    {
        parent::configure();
        $this->setName('mcp:test-server')
            ->setDescription('运行MCP测试服务器')
            ->setHelp('此命令启动一个MCP测试服务器');
    }
}
