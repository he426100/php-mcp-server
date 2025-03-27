<?php

declare(strict_types=1);

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\Base64Service;

class Base64ServerCommand extends AbstractMcpServerCommand
{
    protected string $serverName = 'base64-server';
    protected string $serviceClass = Base64Service::class;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('mcp:base64-server')
            ->setDescription('运行Base64处理服务器')
            ->setHelp('此命令启动一个Base64处理服务器，提供编码、解码和图片转换功能');
    }
}
