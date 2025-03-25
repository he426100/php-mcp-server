<?php

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\LoggerService;
use He426100\McpServer\Service\TestService;

use Mcp\Server\Server;
use Mcp\Server\ServerRunner;

class TestServerCommand extends Command
{
    // 配置命令
    protected function configure(): void
    {
        $this->setName('mcp:test-server')
            ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Port to listen on for SSE', 8000)
            ->addOption('transport', null, InputOption::VALUE_OPTIONAL, 'Transport type', 'stdio')
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

        // 创建服务器实例
        $server = new Server('mcp-test-server');

        // 创建测试服务并注册处理器
        $testService = new TestService();
        $testService->registerHandlers($server);

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
