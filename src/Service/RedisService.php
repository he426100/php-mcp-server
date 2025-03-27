<?php

declare(strict_types=1);

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;
use Redis;

class RedisService
{
    private Redis $redis;
    private string $host;
    private int $port;
    private int $database;

    /**
     * 设置 Redis 配置
     *
     * @param string $host Redis 服务器地址
     * @param int $port Redis 服务器端口
     * @param int $database Redis 数据库编号 (0-15)
     */
    public function setConfig(string $host, int $port = 6379, int $database = 0): void
    {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->initRedis();
    }

    private function initRedis(): void
    {
        $this->redis = new Redis();
        
        if (!$this->redis->connect($this->host, $this->port)) {
            throw new \RuntimeException("无法连接到 Redis 服务器");
        }

        if ($this->database > 0) {
            if (!$this->redis->select($this->database)) {
                throw new \RuntimeException("无法切换到数据库 {$this->database}");
            }
        }
    }

    #[Tool(
        name: 'set',
        description: '设置 Redis 键值对，可选过期时间',
        parameters: [
            'key' => [
                'type' => 'string',
                'description' => 'Redis 键',
                'required' => true
            ],
            'value' => [
                'type' => 'string',
                'description' => '要存储的值',
                'required' => true
            ],
            'expireSeconds' => [
                'type' => 'integer',
                'description' => '可选的过期时间（秒）',
                'required' => false
            ]
        ]
    )]
    public function set(string $key, string $value, ?int $expireSeconds = null): string
    {
        try {
            if ($expireSeconds !== null) {
                $result = $this->redis->setex($key, $expireSeconds, $value);
            } else {
                $result = $this->redis->set($key, $value);
            }

            if (!$result) {
                throw new \RuntimeException("设置键值对失败");
            }

            return "成功设置键: {$key}";
        } catch (\Exception $e) {
            throw new \RuntimeException("Redis 操作失败: " . $e->getMessage());
        }
    }

    #[Tool(
        name: 'get',
        description: '获取 Redis 键的值',
        parameters: [
            'key' => [
                'type' => 'string',
                'description' => '要获取的 Redis 键',
                'required' => true
            ]
        ]
    )]
    public function get(string $key): string
    {
        try {
            $value = $this->redis->get($key);

            if ($value === false) {
                return "未找到键: {$key}";
            }

            return $value;
        } catch (\Exception $e) {
            throw new \RuntimeException("Redis 操作失败: " . $e->getMessage());
        }
    }

    #[Tool(
        name: 'delete',
        description: '删除一个或多个 Redis 键',
        parameters: [
            'key' => [
                'type' => ['string', 'array'],
                'description' => '要删除的键或键数组',
                'required' => true
            ]
        ]
    )]
    public function delete(string|array $key): string
    {
        try {
            if (is_array($key)) {
                $count = $this->redis->del($key);
                return "成功删除 {$count} 个键";
            } else {
                $count = $this->redis->del($key);
                return "成功删除键: {$key}";
            }
        } catch (\Exception $e) {
            throw new \RuntimeException("Redis 操作失败: " . $e->getMessage());
        }
    }

    #[Tool(
        name: 'list',
        description: '列出匹配模式的 Redis 键',
        parameters: [
            'pattern' => [
                'type' => 'string',
                'description' => '匹配键的模式（默认：*）',
                'required' => false
            ]
        ]
    )]
    public function list(string $pattern = '*'): string
    {
        try {
            $keys = $this->redis->keys($pattern);

            if (empty($keys)) {
                return "未找到匹配模式的键";
            }

            return "找到的键:\n" . implode("\n", $keys);
        } catch (\Exception $e) {
            throw new \RuntimeException("Redis 操作失败: " . $e->getMessage());
        }
    }
}
