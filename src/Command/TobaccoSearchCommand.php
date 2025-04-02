<?php

declare(strict_types=1);

namespace He426100\McpServer\Command;

use He426100\McpServer\Service\TobaccoSearchService;
// No need for InputInterface, OutputInterface etc. here if not customizing beyond base

class TobaccoSearchCommand extends AbstractMcpServerCommand
{
    // Define a unique server name
    protected string $serverName = 'mcp-tobacco-search-server';

    // Point to the new service class
    protected string $serviceClass = TobaccoSearchService::class;

    // Configure the command details
    protected function configure(): void
    {
        parent::configure(); // Inherit options like --port and --transport

        $this->setName('mcp:tobacco-search')
            ->setDescription('运行MCP烟草产品搜索服务器 (etmoc.com via Google)')
            ->setHelp('此命令启动一个MCP服务器，提供通过Google搜索etmoc.com烟草产品并抓取信息的功能.');
    }
}
