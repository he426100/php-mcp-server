<?php

declare(strict_types=1);

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            );
    }

    protected function configService(mixed $service, ServerRunner $runner, InputInterface $input, OutputInterface $output)
    {
        $dbPath = getenv('DB_PATH') ?: $input->getOption('db-path') ?: BASE_PATH . '/runtime/sqlite.db';
        /** @var SqliteService $service */
        $service->setConfig($dbPath);
    }

    protected function afterServerRun($service, ServerRunner $runner): void
    {
        $session = $runner->getSession();
        /** @var SqliteService $service */
        $service->setSession($session);
    }
}
