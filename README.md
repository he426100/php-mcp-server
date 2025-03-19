# PHP MCP Server

这是一个基于 PHP 实现的 MCP (Model Control Protocol) 服务器示例项目。

## 项目概述

本项目提供了一个完整的 MCP 服务器实现，包含以下功能：

- Prompt 处理器
- Tool 处理器
- Resource 处理器
- 完整的日志系统
- Docker 支持

## 系统要求

- PHP >= 8.1
- Composer
- Docker (可选)

## 安装

### 方式一：本地安装

1. 克隆项目

```bash
git clone https://github.com/he426100/php-mcp-server
cd php-mcp-server
```

2. 安装依赖

```bash
composer install
```

3. 运行服务器

```bash
php bin/console
```

### 方式二：Docker 安装

1. 构建镜像

```bash
docker build -t php-mcp-server .
```

2. 运行容器

```bash
docker run -i --rm php-mcp-server
```

## 项目结构

```
.
├── bin/
│   └── console              # 命令行入口文件
├── src/
│   ├── Command/
│   │   └── TestServerCommand.php    # MCP 服务器命令实现
│   └── Service/
│       └── LoggerService.php        # 日志服务
├── runtime/                 # 运行时文件目录
├── composer.json           # Composer 配置文件
├── Dockerfile             # Docker 构建文件
└── README.md              # 项目说明文档
```

## 功能说明

### 1. Prompt 处理器

提供示例提示模板，支持：
- 列出可用的提示模板
- 获取特定提示模板内容

### 2. Tool 处理器

实现了一个简单的数字加法工具，支持：
- 列出可用工具
- 调用工具执行操作

### 3. Resource 处理器

提供基础资源访问功能，支持：
- 列出可用资源
- 读取资源内容

## 配置说明

### 环境变量

- `PHP_MEMORY_LIMIT`: PHP 内存限制 (默认: 1G)
- `PHP_TIMEZONE`: 时区设置 (默认: PRC)

### 日志配置

日志文件位于 `runtime/server_log.txt`，可通过 `LoggerService` 配置：

```php
LoggerService::createLogger(
    'php-mcp-server',
    BASE_PATH . '/runtime/server_log.txt',
    false
);
```

## 开发

### 代码规范

项目使用 PHP_CodeSniffer 进行代码规范检查：

```bash
./vendor/bin/phpcs
```

### 静态分析

使用 PHPStan 进行静态代码分析：

```bash
composer analyse
```

## 测试

运行单元测试：

```bash
./vendor/bin/phpunit
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
