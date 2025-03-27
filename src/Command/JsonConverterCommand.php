<?php

declare(strict_types=1);

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\JsonConverterService;

class JsonConverterCommand extends AbstractMcpServerCommand
{
    protected string $serverName = 'json-converter-server';
    protected string $serviceClass = JsonConverterService::class;
    protected string $logFilePath = BASE_PATH . '/runtime/json_converter_log.txt';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('mcp:json-converter')
            ->setDescription('运行JSON转换工具服务器')
            ->setHelp('此命令启动一个JSON转换工具服务器，提供JSON与其他格式之间的转换功能')
            ->addOption(
                'debug',
                null,
                InputOption::VALUE_NONE,
                '启用调试模式，输出更多日志信息'
            );
    }
}
