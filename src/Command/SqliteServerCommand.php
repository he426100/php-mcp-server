<?php

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = $input->getOption('transport');
        if (!in_array($transport, ['stdio', 'sse'])) {
            throw new \Exception('Unsupported transport: ' . $transport);
        }

        $port = $input->getOption('port');
        $dbPath = $input->getOption('db-path');

        // 创建日志记录器
        $logger = LoggerService::createLogger(
            $this->serverName,
            $this->logFilePath,
            false
        );

        // 创建服务器实例
        $server = new Server($this->serverName);

        // 配置服务
        /** @var SqliteService */
        $service = new $this->serviceClass();
        $registrar = new McpHandlerRegistrar();
        $registrar->registerHandler($server, $service);

        $service->setConfig($dbPath);

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
