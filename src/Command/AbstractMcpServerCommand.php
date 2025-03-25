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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = $input->getOption('transport');
        if (!in_array($transport, ['stdio', 'sse'])) {
            throw new \Exception('Unsupported transport: ' . $transport);
        }

        $port = $input->getOption('port');

        // 创建日志记录器
        $logger = LoggerService::createLogger(
            $this->serverName,
            $this->logFilePath,
            false
        );

        // 创建服务器实例
        $server = new Server($this->serverName);

        // 配置服务
        $className = $this->serviceClass;
        (new $className)->registerHandlers($server);

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
