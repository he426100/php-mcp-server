<?php

namespace He426100\McpServer\Service;

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class LoggerService
{
    /**
     * 创建日志记录器
     *
     * @param string $name 日志记录器名称
     * @param string $logFile 日志文件路径
     * @param bool $resetLog 是否重置日志文件
     * @return Logger
     */
    public static function createLogger(string $name, string $logFile, bool $resetLog = false): Logger
    {
        // 创建日志记录器
        $logger = new Logger($name);

        // 如果需要，删除之前的日志
        if ($resetLog) {
            @unlink($logFile);
        }

        // 创建处理器
        $handler = new StreamHandler($logFile, Level::Debug);

        // 创建自定义格式化器使日志更易读
        $dateFormat = "Y-m-d H:i:s";
        $output = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $handler->setFormatter($formatter);

        // 将处理器添加到日志记录器
        $logger->pushHandler($handler);

        return $logger;
    }
}
