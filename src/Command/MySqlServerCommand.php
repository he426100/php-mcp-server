<?php

namespace He426100\McpServer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use He426100\McpServer\Service\LoggerService;
use Mcp\Tool\McpHandlerRegistrar;
use Mcp\Server\Server;
use Mcp\Server\ServerRunner;
use He426100\McpServer\Service\MySqlService;

class MySqlServerCommand extends AbstractMcpServerCommand
{
    protected string $serverName = 'mysql-mcp-server';
    protected string $serviceClass = MySqlService::class;

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('mcp:mysql-server')
            ->setDescription('运行MySQL工具服务器')
            ->setHelp('此命令启动一个MySQL工具服务器，提供数据库查询服务')
            ->addOption(
                'host',
                null,
                InputOption::VALUE_REQUIRED,
                '数据库主机',
                'localhost'
            )
            ->addOption(
                'db-port',
                null,
                InputOption::VALUE_REQUIRED,
                '数据库端口',
                '3306'
            )
            ->addOption(
                'username',
                'u',
                InputOption::VALUE_REQUIRED,
                '数据库用户名',
                'root'
            )
            ->addOption(
                'password',
                'p',
                InputOption::VALUE_REQUIRED,
                '数据库密码',
                ''
            )
            ->addOption(
                'database',
                'd',
                InputOption::VALUE_REQUIRED,
                '数据库名称',
                'mysql'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $transport = $input->getOption('transport');
        if (!in_array($transport, ['stdio', 'sse'])) {
            throw new \Exception('Unsupported transport: ' . $transport);
        }

        $ssePort = $input->getOption('port');

        $host = getenv('DB_HOST') ?: $input->getOption('host') ?: 'localhost';
        $port = (int)(getenv('DB_PORT') ?: $input->getOption('db-port') ?: 3306);
        $username = getenv('DB_USERNAME') ?: $input->getOption('username') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: $input->getOption('password') ?: '';
        $database = getenv('DB_DATABASE') ?: $input->getOption('database') ?: 'mysql';

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
        /** @var MySqlService */
        $service = new $className();
        $registrar = new McpHandlerRegistrar();
        $registrar->registerHandler($server, $service);

        $service->setConfig($host, $username, $password, $database, $port);

        // 创建初始化选项并运行服务器
        $initOptions = $server->createInitializationOptions();
        $runner = new ServerRunner($logger, $transport, '0.0.0.0', $ssePort);

        try {
            $runner->run($server, $initOptions);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $logger->error("服务器运行失败", ['exception' => $e]);
            return Command::FAILURE;
        }
    }
}
