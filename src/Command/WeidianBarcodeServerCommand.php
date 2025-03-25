<?php

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\LoggerService;
use He426100\McpServer\Service\WeidianBarcodeService;

use Mcp\Server\Server;
use Mcp\Server\ServerRunner;

class WeidianBarcodeServerCommand extends AbstractMcpServerCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('weidian:barcode-query')
            ->setDescription('运行微店条码查询MCP服务器')
            ->setHelp('此命令启动一个微店条码查询MCP服务器');
    }

    protected function getServerName(): string
    {
        return 'weidian-barcode-server';
    }

    protected function getLogFilePath(): string
    {
        return BASE_PATH . '/runtime/weidian_barcode_server.log';
    }

    protected function configureService(Server $server): void
    {
        // 创建微店条码服务并注册处理器
        $barcodeService = new WeidianBarcodeService();
        $barcodeService->registerHandlers($server);
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
            $this->getServerName(),
            $this->getLogFilePath(),
            false
        );

        // 创建服务器实例
        $server = new Server($this->getServerName());

        // 配置服务
        $this->configureService($server);

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
