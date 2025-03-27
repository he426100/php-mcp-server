<?php

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\RedisService;
use Mcp\Server\ServerRunner;

class RedisServerCommand extends AbstractMcpServerCommand
{
    protected string $serverName = 'redis-mcp-server';
    protected string $serviceClass = RedisService::class;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('mcp:redis-server')
            ->setDescription('运行 Redis MCP 服务器')
            ->setHelp('此命令启动一个 Redis MCP 服务器，提供 Redis 操作功能')
            ->addOption(
                'host',
                null,
                InputOption::VALUE_REQUIRED,
                'Redis 服务器地址',
                'localhost'
            )
            ->addOption(
                'db-port',
                null,
                InputOption::VALUE_REQUIRED,
                'Redis 服务器端口',
                '6379'
            )
            ->addOption(
                'database',
                null,
                InputOption::VALUE_REQUIRED,
                'Redis 数据库编号 (0-15)',
                '0'
            );
    }

    protected function configService(mixed $service, ServerRunner $runner, InputInterface $input, OutputInterface $output)
    {
        $host = getenv('REDIS_HOST') ?: $input->getOption('host');
        $port = (int)(getenv('REDIS_PORT') ?: $input->getOption('db-port'));
        $database = (int)(getenv('REDIS_DATABASE') ?: $input->getOption('database'));

        /** @var RedisService $service */
        $service->setConfig($host, $port, $database);
    }
}
