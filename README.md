# PHP MCP Server

[English Version](README.en.md)

这是一个基于 PHP 实现的 MCP (Model Control Protocol) 服务器框架，支持通过注解优雅地定义 MCP 服务。

## 项目概述

本项目提供了一个完整的 MCP 服务器实现，特色功能： 

- 基于注解的 MCP 服务定义  
- 支持 Tool、Prompt、Resource 三种处理器  
- 支持 Stdio、Sse 两种传输方式  
- 支持 [Swow](https://github.com/swow/swow) 和 [Swoole](https://github.com/swoole/swoole-src) 两种环境  
- 完整的日志系统  
- Docker 支持  

## 系统要求

- PHP >= 8.1
- Composer
- Swow 扩展 > 1.5 或 Swoole > 5.1
- Docker (可选)

## 快速开始

### 安装

```bash
# 1. 克隆项目
git clone https://github.com/he426100/php-mcp-server
cd php-mcp-server

# 2. 安装依赖
composer install

# 3. 可选，安装 Swow 扩展（如果没有）
./vendor/bin/swow-builder --install
```

> 关于 Swow 扩展的详细安装说明,请参考 [Swow 官方文档](https://github.com/swow/swow)

### 运行示例服务器

```bash
php bin/console mcp:test-server
```

#### 通用命令参数

| Parameter | Description | Default Value | Options |
|-----------|-------------|---------------|---------|
| --transport | Transport type | stdio | stdio, sse |
| --port | Port to listen on for SSE | 8000 |  |


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

## 注解函数返回类型说明

### Tool 注解函数支持的返回类型

| 返回类型 | 说明 | 转换结果 |
|---------|------|---------|
| TextContent/ImageContent/EmbeddedResource | 直接返回内容对象 | 原样保留 |
| TextContent/ImageContent/EmbeddedResource 数组 | 内容对象数组 | 原样保留 |
| ResourceContents | 资源内容对象 | 转换为 EmbeddedResource |
| 字符串或标量类型 | 如 string、int、float、bool | 转换为 TextContent |
| null | 空值 | 转换为空字符串的 TextContent |
| 数组或对象 | 复杂数据结构 | 转换为 JSON 格式的 TextContent |

### Prompt 注解函数支持的返回类型

| 返回类型 | 说明 | 转换结果 |
|---------|------|---------|
| PromptMessage | 消息对象 | 原样保留 |
| PromptMessage 数组 | 消息对象数组 | 原样保留 |
| Content 对象 | TextContent/ImageContent 等 | 转换为用户角色的 PromptMessage |
| 字符串或标量类型 | 如 string、int、float、bool | 转换为带 TextContent 的用户消息 |
| null | 空值 | 转换为空内容的用户消息 |
| 数组或对象 | 复杂数据结构 | 转换为 JSON 格式的用户消息 |

### Resource 注解函数支持的返回类型

| 返回类型 | 说明 | 转换结果 |
|---------|------|---------|
| TextResourceContents/BlobResourceContents | 资源内容对象 | 原样保留 |
| ResourceContents 数组 | 资源内容对象数组 | 原样保留 |
| 字符串或可转字符串对象 | 文本内容 | 根据 MIME 类型转换为对应资源内容 |
| null | 空值 | 转换为空的 TextResourceContents |
| 数组或对象 | 复杂数据结构 | 转换为 JSON 格式的资源内容 |

注意事项：
- 对于超过 2MB 的大文件内容会自动截断
- 文本类型 (text/*) 的 MIME 类型会使用 TextResourceContents
- 其他 MIME 类型会使用 BlobResourceContents

## 日志配置

服务器日志默认保存在 `runtime/server_log.txt`，可通过继承 `AbstractMcpServerCommand` 修改：

```php
protected string $logFilePath = '/custom/path/to/log.txt';
```

## Docker 支持

构建并运行容器：

```bash
docker build -t php-mcp-server .
docker run --name=php-mcp-server -p 8000:8000 -itd php-mcp-server mcp:test-server --transport sse
```

sse地址：http://127.0.0.1:8000/sse

## 通过 CPX 使用

您可以通过 [CPX (Composer Package Executor)](https://github.com/imliam/cpx) 直接运行本项目，无需事先安装：

### 前提条件

1. 全局安装 CPX：
```bash
composer global require cpx/cpx
```

2. 确保 Composer 的全局 bin 目录在您的 PATH 中

### 使用方式

```bash
# 运行测试服务器
cpx he426100/php-mcp-server mcp:test-server

# 使用 SSE 传输模式
cpx he426100/php-mcp-server mcp:test-server --transport=sse

# 查看可用命令
cpx he426100/php-mcp-server list
```

### 优点

- 无需克隆仓库或手动安装项目
- 自动获取最新的稳定版本
- 不会与您的其他项目或全局依赖冲突

## 许可证

[MIT License](LICENSE)

## 贡献

欢迎提交 Issue 和 Pull Request。

## 作者

[he426100](https://github.com/he426100/)  
[logiscape](https://github.com/logiscape/mcp-sdk-php)
