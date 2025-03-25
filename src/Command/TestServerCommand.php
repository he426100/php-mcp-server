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
