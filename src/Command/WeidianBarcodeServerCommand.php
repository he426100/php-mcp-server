<?php

declare(strict_types=1);

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
    protected string $serverName = 'weidian-barcode-server';
    protected string $serviceClass = WeidianBarcodeService::class;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('weidian:barcode-query')
            ->setDescription('运行微店条码查询MCP服务器')
            ->setHelp('此命令启动一个微店条码查询MCP服务器');
    }
}
