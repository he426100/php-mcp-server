<?php

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;
use PDO;

class MySqlService
{
    private string $host;
    private string $username;
    private string $password;
    private string $database;
    private int $port;

    /**
     * 设置数据库配置
     */
    public function setConfig(
        string $host,
        string $username,
        string $password,
        string $database,
        int $port
    ): void {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->port = $port;
    }

    #[Tool(
        name: 'list_tables',
        description: '列出数据库中的所有表'
    )]
    public function listTables(): string
    {
        $pdo = $this->getDatabaseConnection();
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 限制结果集大小以防止内存问题
        if (count($tables) > 1000) {
            $tables = array_slice($tables, 0, 1000);
            $tablesText = "数据库表列表 (仅显示前1000个):\n\n";
        } else {
            $tablesText = "数据库表列表:\n\n";
        }

        $tablesText .= "| 序号 | 表名 |\n";
        $tablesText .= "|------|------|\n";

        foreach ($tables as $index => $table) {
            $tablesText .= "| " . ($index + 1) . " | " . $table . " |\n";
        }

        return $tablesText;
    }

    #[Tool(
        name: 'describe-table',
        description: '描述指定表的结构',
        parameters: [
            'tableName' => [
                'type' => 'string',
                'description' => '表名',
                'required' => true
            ]
        ]
    )]
    public function describeTable(string $tableName): string
    {
        $pdo = $this->getDatabaseConnection();

        // 验证表名以防止SQL注入
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            throw new \InvalidArgumentException("无效的表名");
        }

        $stmt = $pdo->prepare('DESCRIBE ' . $tableName);
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($columns)) {
            throw new \Exception("表 '{$tableName}' 不存在或没有列");
        }

        // 将表结构格式化为表格字符串
        $tableDesc = "表 '{$tableName}' 的结构:\n\n";
        $tableDesc .= "| 字段 | 类型 | 允许为空 | 键 | 默认值 | 额外 |\n";
        $tableDesc .= "|------|------|----------|-----|--------|------|\n";

        foreach ($columns as $column) {
            $tableDesc .= "| " . $column['Field'] . " | "
                . $column['Type'] . " | "
                . $column['Null'] . " | "
                . $column['Key'] . " | "
                . ($column['Default'] === null ? 'NULL' : $column['Default']) . " | "
                . $column['Extra'] . " |\n";
        }

        return $tableDesc;
    }

    #[Tool(
        name: 'read_query',
        description: '执行SQL查询并返回结果',
        parameters: [
            'sql' => [
                'type' => 'string',
                'description' => 'SQL查询语句',
                'required' => true
            ]
        ]
    )]
    public function readQuery(string $sql): string
    {
        $pdo = $this->getDatabaseConnection();

        // 只允许SELECT查询以确保安全
        if (!preg_match('/^SELECT\s/i', trim($sql))) {
            throw new \InvalidArgumentException("只允许SELECT查询");
        }

        // 限制查询以防止大型结果集
        if (strpos(strtoupper($sql), 'LIMIT') === false) {
            $sql .= ' LIMIT 1000';
            $limitAdded = true;
        } else {
            $limitAdded = false;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return "查询执行成功，但没有返回结果。";
        }

        // 从结果中提取列名
        $columns = array_keys($results[0]);

        // 构建表格标题
        $resultText = "查询结果";
        if ($limitAdded) {
            $resultText .= " (已自动添加LIMIT 1000)";
        }
        $resultText .= ":\n\n";

        $resultText .= "| " . implode(" | ", $columns) . " |\n";
        $resultText .= "| " . implode(" | ", array_map(function ($col) {
            return str_repeat("-", mb_strlen($col));
        }, $columns)) . " |\n";

        // 添加数据行
        $rowCount = 0;
        $maxRows = 100; // 限制显示的行数

        foreach ($results as $row) {
            if ($rowCount++ >= $maxRows) {
                break;
            }

            $resultText .= "| " . implode(" | ", array_map(function ($val) {
                if ($val === null) {
                    return 'NULL';
                } elseif (is_string($val) && mb_strlen($val) > 100) {
                    return mb_substr($val, 0, 97) . '...';
                } else {
                    return (string)$val;
                }
            }, $row)) . " |\n";
        }

        $totalRows = count($results);
        $resultText .= "\n共返回 " . $totalRows . " 条记录";

        if ($rowCount < $totalRows) {
            $resultText .= "，仅显示前 " . $rowCount . " 条";
        }

        return $resultText;
    }

    /**
     * 获取数据库连接
     */
    private function getDatabaseConnection(): PDO
    {
        // 验证环境变量
        if (!$this->username || !$this->database) {
            throw new \Exception("数据库连接信息不完整，请设置必要的环境变量");
        }

        try {
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            return new PDO($dsn, $this->username, $this->password, $options);
        } catch (\PDOException $e) {
            throw new \Exception("数据库连接失败: " . $e->getMessage());
        }
    }
}
