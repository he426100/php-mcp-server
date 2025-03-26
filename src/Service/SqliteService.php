<?php

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;
use Mcp\Annotation\Resource;
use PDO;

class SqliteService
{
    private PDO $pdo;
    private array $insights = [];
    private string $dbPath;

    public function setConfig(string $dbPath): void
    {
        $this->dbPath = $dbPath;
        $this->initDatabase();
    }

    private function initDatabase(): void
    {
        if (!file_exists(dirname($this->dbPath))) {
            mkdir(dirname($this->dbPath), 0777, true);
        }

        $this->pdo = new PDO("sqlite:{$this->dbPath}");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    #[Tool(
        name: 'read_query',
        description: '执行 SELECT 查询并返回结果',
        parameters: [
            'query' => [
                'type' => 'string',
                'description' => 'SELECT SQL 查询语句',
                'required' => true
            ]
        ]
    )]
    public function readQuery(string $query): string
    {
        if (!preg_match('/^SELECT\s/i', trim($query))) {
            throw new \InvalidArgumentException("只允许 SELECT 查询");
        }

        $stmt = $this->pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return "查询执行成功，但没有返回结果。";
        }

        return $this->formatResults($results);
    }

    #[Tool(
        name: 'write_query',
        description: '执行 INSERT、UPDATE 或 DELETE 查询',
        parameters: [
            'query' => [
                'type' => 'string',
                'description' => 'SQL 写入查询语句',
                'required' => true
            ]
        ]
    )]
    public function writeQuery(string $query): string
    {
        if (preg_match('/^SELECT\s/i', trim($query))) {
            throw new \InvalidArgumentException("不允许 SELECT 查询");
        }

        $affected = $this->pdo->exec($query);
        return "执行成功，影响 {$affected} 行。";
    }

    #[Tool(
        name: 'create_table',
        description: '创建新表',
        parameters: [
            'query' => [
                'type' => 'string',
                'description' => 'CREATE TABLE SQL 语句',
                'required' => true
            ]
        ]
    )]
    public function createTable(string $query): string
    {
        if (!preg_match('/^CREATE\s+TABLE\s/i', trim($query))) {
            throw new \InvalidArgumentException("只允许 CREATE TABLE 语句");
        }

        $this->pdo->exec($query);
        return "表创建成功";
    }

    #[Tool(
        name: 'list_tables',
        description: '列出所有表'
    )]
    public function listTables(): string
    {
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($tables)) {
            return "数据库中没有表。";
        }

        return "数据库表:\n" . implode("\n", $tables);
    }

    #[Tool(
        name: 'describe_table',
        description: '显示表结构',
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
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            throw new \InvalidArgumentException("无效的表名");
        }

        $stmt = $this->pdo->query("PRAGMA table_info('{$tableName}')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($columns)) {
            return "表 '{$tableName}' 不存在或没有列";
        }

        $result = "表 '{$tableName}' 结构:\n";
        $result .= "| 名称 | 类型 | 可空 | 默认值 | 主键 |\n";
        $result .= "|------|------|------|--------|------|\n";

        foreach ($columns as $column) {
            $result .= sprintf(
                "| %s | %s | %s | %s | %s |\n",
                $column['name'],
                $column['type'],
                $column['notnull'] ? '否' : '是',
                $column['dflt_value'] ?? 'NULL',
                $column['pk'] ? '是' : '否'
            );
        }

        return $result;
    }

    #[Tool(
        name: 'append_insight',
        description: '添加业务洞察',
        parameters: [
            'insight' => [
                'type' => 'string',
                'description' => '业务洞察内容',
                'required' => true
            ]
        ]
    )]
    public function appendInsight(string $insight): string
    {
        $this->insights[] = $insight;
        return "洞察已添加到备忘录";
    }

    #[Resource(
        uri: 'memo://insights',
        name: '业务洞察备忘录',
        description: '已发现的业务洞察集合'
    )]
    public function getInsights(): string
    {
        if (empty($this->insights)) {
            return "尚未发现业务洞察。";
        }

        $memo = "📊 业务洞察备忘录 📊\n\n";
        $memo .= "关键发现:\n\n";

        foreach ($this->insights as $insight) {
            $memo .= "- {$insight}\n";
        }

        if (count($this->insights) > 1) {
            $memo .= "\n总结:\n";
            $memo .= "分析发现了 " . count($this->insights) . " 个关键业务洞察，表明存在战略优化和增长机会。";
        }

        return $memo;
    }

    private function formatResults(array $results): string
    {
        $columns = array_keys($results[0]);

        $output = "| " . implode(" | ", $columns) . " |\n";
        $output .= "|" . str_repeat("---|", count($columns)) . "\n";

        foreach ($results as $row) {
            $output .= "| " . implode(" | ", array_map(function ($val) {
                return $val === null ? 'NULL' : (string)$val;
            }, $row)) . " |\n";
        }

        return $output;
    }
}
