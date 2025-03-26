<?php

namespace He426100\McpServer\Tool;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

/**
 * 命令发现和注册工具类
 */
class CommandDiscoverer
{
    /**
     * 发现并注册命令到应用程序
     * 
     * @param Application $application Symfony Console应用程序实例
     * @param string $commandsDir 命令目录路径
     * @param string $namespace 命令类的命名空间
     * @return int 成功注册的命令数量
     */
    public static function discoverAndRegister(
        Application $application,
        string $commandsDir,
        string $namespace = 'He426100\\McpServer\\Command\\'
    ): void {
        $commandClasses = self::discoverCommands($commandsDir, $namespace);
        self::registerCommands($application, $commandClasses);
    }

    /**
     * 发现目录中的命令类
     * 
     * @param string $commandsDir 命令目录路径
     * @param string $namespace 命令类的命名空间
     * @return array 发现的命令类列表
     */
    public static function discoverCommands(
        string $commandsDir,
        string $namespace = 'He426100\\McpServer\\Command\\'
    ): array {
        $commands = [];

        // 确保目录存在
        if (!is_dir($commandsDir)) {
            return $commands;
        }

        // 获取所有PHP文件
        $files = glob($commandsDir . '/*.php');

        foreach ($files as $file) {
            // 获取文件名（不含扩展名）
            $className = basename($file, '.php');
            $fullyQualifiedClassName = $namespace . $className;

            // 检查类是否存在
            if (!class_exists($fullyQualifiedClassName)) {
                continue;
            }

            // 使用反射检查类
            $reflectionClass = new \ReflectionClass($fullyQualifiedClassName);

            // 跳过抽象类
            if ($reflectionClass->isAbstract()) {
                continue;
            }

            // 确保类是Command的子类
            if ($reflectionClass->isSubclassOf(Command::class)) {
                $commands[] = $fullyQualifiedClassName;
            }
        }

        return $commands;
    }

    /**
     * 注册命令到应用程序
     * 
     * @param Application $application Symfony Console应用程序实例
     * @param array $commandClasses 命令类列表
     * @return void
     */
    public static function registerCommands(Application $application, array $commandClasses): void
    {
        foreach ($commandClasses as $commandClass) {
            try {
                $command = new $commandClass();
                $application->add($command);
            } catch (\Exception $e) {
                // 记录错误但继续注册其他命令
                error_log("无法加载命令类 {$commandClass}: " . $e->getMessage());
            }
        }
    }
}
