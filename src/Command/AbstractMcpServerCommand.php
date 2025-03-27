<?php

declare(strict_types=1);

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\BaseService;
use He426100\McpServer\Service\LoggerService;
use Mcp\Tool\McpHandlerRegistrar;
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

    /**
     * 
     * @param ServerRunner $runner 
     * @param mixed $service 
     * @param InputInterface $input 
     * @param OutputInterface $output 
     * @return void 
     */
    protected function configService(mixed $service, ServerRunner $runner, InputInterface $input, OutputInterface $output) {}

    /**
     * 
     * @param mixed $service
     * @param ServerRunner $runner
     */
    protected function afterServerRun($service, ServerRunner $runner): void {}

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
        /** @var BaseService $service */
        $service = new $className($logger);

        // 创建运行器并配置服务
        $runner = new ServerRunner($logger, $transport, '0.0.0.0', $port);
        $this->configService($service, $runner, $input, $output);  // 先配置服务

        $registrar = new McpHandlerRegistrar();
        $registrar->registerHandler($server, $service);

        // 创建初始化选项并运行服务器
        $initOptions = $server->createInitializationOptions();

        try {
            $runner->run($server, $initOptions);
            $this->afterServerRun($service, $runner);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $logger->error("服务器运行失败", ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
