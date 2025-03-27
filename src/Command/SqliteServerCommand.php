<?php

declare(strict_types=1);

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\LoggerService;
use Mcp\Tool\McpHandlerRegistrar;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use He426100\McpServer\Service\SqliteService;

class SqliteServerCommand extends AbstractMcpServerCommand
{
    protected string $serverName = 'sqlite-mcp-server';
    protected string $serviceClass = SqliteService::class;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('mcp:sqlite-server')
            ->setDescription('运行 SQLite MCP 服务器')
            ->setHelp('此命令启动一个 SQLite MCP 服务器，提供数据库操作功能')
            ->addOption(
                'db-path',
                null,
                InputOption::VALUE_REQUIRED,
                'SQLite 数据库文件路径',
                BASE_PATH . '/runtime/sqlite.db'
            );
    }

    protected function configService(mixed $service, ServerRunner $runner, InputInterface $input, OutputInterface $output)
    {
        $dbPath = $input->getOption('db-path');
        /** @var SqliteService $service */
        $service->setConfig($dbPath);

        $session = $runner->getSession();
        $service->setSession($session);
    }
}
