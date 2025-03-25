# PHP MCP Server

这是一个基于 PHP 实现的 MCP (Model Control Protocol) 服务器框架，支持通过注解优雅地定义 MCP 服务。

## 项目概述

本项目提供了一个完整的 MCP 服务器实现，特色功能：

- 基于注解的 MCP 服务定义
- 支持 Tool、Prompt、Resource 三种处理器
- 完整的日志系统
- Docker 支持

## 系统要求

- PHP >= 8.1
- Composer
- Docker (可选)

## 快速开始

### 安装

```bash
git clone https://github.com/he426100/php-mcp-server
cd php-mcp-server
composer install
```

### 运行示例服务器

```bash
php bin/console mcp:test-server
```

## 注解使用指南

本框架提供三种核心注解用于定义 MCP 服务：

### 1. Tool 注解

用于定义工具类处理器：

```php
use Mcp\Annotation\Tool;

class MyService {
    #[Tool(
        name: 'calculate-sum',
        description: '计算两个数的和',
        parameters: [
            'num1' => [
                'type' => 'number',
                'description' => '第一个数字',
                'required' => true
            ],
            'num2' => [
                'type' => 'number',
                'description' => '第二个数字',
                'required' => true
            ]
        ]
    )]
    public function sum(int $num1, int $num2): int 
    {
        return $num1 + $num2;
    }
}
```

### 2. Prompt 注解

用于定义提示模板处理器：

```php
use Mcp\Annotation\Prompt;

class MyService {
    #[Prompt(
        name: 'greeting',
        description: '生成问候语',
        arguments: [
            'name' => [
                'description' => '要问候的人名',
                'required' => true
            ]
        ]
    )]
    public function greeting(string $name): string 
    {
        return "Hello, {$name}!";
    }
}
```

### 3. Resource 注解

用于定义资源处理器：

```php
use Mcp\Annotation\Resource;

class MyService {
    #[Resource(
        uri: 'example://greeting',
        name: 'Greeting Text',
        description: '问候语资源',
        mimeType: 'text/plain'
    )]
    public function getGreeting(): string 
    {
        return "Hello from MCP server!";
    }
}
```

## 创建自定义服务

1. 创建服务类：

```php
namespace Your\Namespace;

use Mcp\Annotation\Tool;
use Mcp\Annotation\Prompt;
use Mcp\Annotation\Resource;

class CustomService 
{
    #[Tool(name: 'custom-tool', description: '自定义工具')]
    public function customTool(): string 
    {
        return "Custom tool result";
    }
}
```

2. 创建命令类：

```php
namespace Your\Namespace\Command;

use He426100\McpServer\Command\AbstractMcpServerCommand;
use Your\Namespace\CustomService;

class CustomServerCommand extends AbstractMcpServerCommand 
{
    protected string $serverName = 'custom-server';
    protected string $serviceClass = CustomService::class;

    protected function configure(): void 
    {
        parent::configure();
        $this->setName('custom:server')
            ->setDescription('运行自定义 MCP 服务器');
    }
}
```

3. 注册命令：

在 `composer.json` 中添加：

```json
{
    "autoload": {
        "psr-4": {
            "Your\\Namespace\\": "src/"
        }
    }
}
```

## 注解参数说明

### Tool 注解参数

| 参数 | 类型 | 说明 | 必填 |
|------|------|------|------|
| name | string | 工具名称 | 是 |
| description | string | 工具描述 | 是 |
| parameters | array | 参数定义 | 否 |

### Prompt 注解参数

| 参数 | 类型 | 说明 | 必填 |
|------|------|------|------|
| name | string | 提示模板名称 | 是 |
| description | string | 提示模板描述 | 是 |
| arguments | array | 参数定义 | 否 |

### Resource 注解参数

| 参数 | 类型 | 说明 | 必填 |
|------|------|------|------|
| uri | string | 资源URI | 是 |
| name | string | 资源名称 | 是 |
| description | string | 资源描述 | 是 |
| mimeType | string | MIME类型 | 否 |

## 日志配置

服务器日志默认保存在 `runtime/server_log.txt`，可通过继承 `AbstractMcpServerCommand` 修改：

```php
protected string $logFilePath = '/custom/path/to/log.txt';
```

## Docker 支持

构建并运行容器：

```bash
docker build -t php-mcp-server .
docker run -i --rm php-mcp-server
```

## 许可证

[MIT License](LICENSE)

## 贡献

欢迎提交 Issue 和 Pull Request。

## 作者

[he426100](https://github.com/he426100/)  
[logiscape](https://github.com/logiscape/mcp-sdk-php)

## 更新日志

### v1.0.0
- 初始版本发布
- 实现基础 MCP 服务器功能
- 添加 Docker 支持
