<?php

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\LoggerService;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;

abstract class AbstractMcpServerCommand extends Command
{
    protected string $serviceClass;
    protected string $serverName = '';
    protected string $logFilePath = BASE_PATH . '/runtime/server_log.txt';

    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen on for SSE', 8000)
            ->addOption('transport', null, InputOption::VALUE_OPTIONAL, 'Transport type', 'stdio');
    }
}
