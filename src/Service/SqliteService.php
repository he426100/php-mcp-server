<?php

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;
use Mcp\Annotation\Resource;
use Mcp\Annotation\Prompt;
use Mcp\Server\ServerSession;
use Mcp\Types\GetPromptResult;
use Mcp\Types\PromptMessage;
use Mcp\Types\TextContent;
use Mcp\Types\Role;
use PDO;
use PDOException;
use InvalidArgumentException;
use RuntimeException;

class SqliteService
{
    private PDO $pdo;
    private array $insights = [];
    private string $dbPath;
    private ?ServerSession $session = null;
    
    private const DEMO_PROMPT_TEMPLATE = <<<TEMPLATE
The assistants goal is to walkthrough an informative demo of MCP. To demonstrate the Model Context Protocol (MCP) we will leverage this example server to interact with an SQLite database.
It is important that you first explain to the user what is going on. The user has downloaded and installed the SQLite MCP Server and is now ready to use it.
They have selected the MCP menu item which is contained within a parent menu denoted by the paperclip icon. Inside this menu they selected an icon that illustrates two electrical plugs connecting. This is the MCP menu.
Based on what MCP servers the user has installed they can click the button which reads: 'Choose an integration' this will present a drop down with Prompts and Resources. The user has selected the prompt titled: 'mcp-demo'.
This text file is that prompt. The goal of the following instructions is to walk the user through the process of using the 3 core aspects of an MCP server. These are: Prompts, Tools, and Resources.
They have already used a prompt and provided a topic. The topic is: {topic}. The user is now ready to begin the demo.
Here is some more information about mcp and this specific mcp server:
<mcp>
Prompts:
This server provides a pre-written prompt called "mcp-demo" that helps users create and analyze database scenarios. The prompt accepts a "topic" argument and guides users through creating tables, analyzing data, and generating insights. For example, if a user provides "retail sales" as the topic, the prompt will help create relevant database tables and guide the analysis process. Prompts basically serve as interactive templates that help structure the conversation with the LLM in a useful way.
Resources:
This server exposes one key resource: "memo://insights", which is a business insights memo that gets automatically updated throughout the analysis process. As users analyze the database and discover insights, the memo resource gets updated in real-time to reflect new findings. Resources act as living documents that provide context to the conversation.
Tools:
This server provides several SQL-related tools:
"read_query": Executes SELECT queries to read data from the database
"write_query": Executes INSERT, UPDATE, or DELETE queries to modify data
"create_table": Creates new tables in the database
"list_tables": Shows all existing tables
"describe_table": Shows the schema for a specific table
"append_insight": Adds a new business insight to the memo resource
</mcp>
<demo-instructions>
You are an AI assistant tasked with generating a comprehensive business scenario based on a given topic.
Your goal is to create a narrative that involves a data-driven business problem, develop a database structure to support it, generate relevant queries, create a dashboard, and provide a final solution.

At each step you will pause for user input to guide the scenario creation process. Overall ensure the scenario is engaging, informative, and demonstrates the capabilities of the SQLite MCP Server.
You should guide the scenario to completion. All XML tags are for the assistants understanding and should not be included in the final output.

1. The user has chosen the topic: {topic}.

2. Create a business problem narrative:
a. Describe a high-level business situation or problem based on the given topic.
b. Include a protagonist (the user) who needs to collect and analyze data from a database.
c. Add an external, potentially comedic reason why the data hasn't been prepared yet.
d. Mention an approaching deadline and the need to use Claude (you) as a business tool to help.

3. Setup the data:
a. Instead of asking about the data that is required for the scenario, just go ahead and use the tools to create the data. Inform the user you are "Setting up the data".
b. Design a set of table schemas that represent the data needed for the business problem.
c. Include at least 2-3 tables with appropriate columns and data types.
d. Leverage the tools to create the tables in the SQLite database.
e. Create INSERT statements to populate each table with relevant synthetic data.
f. Ensure the data is diverse and representative of the business problem.
g. Include at least 10-15 rows of data for each table.

4. Pause for user input:
a. Summarize to the user what data we have created.
b. Present the user with a set of multiple choices for the next steps.
c. These multiple choices should be in natural language, when a user selects one, the assistant should generate a relevant query and leverage the appropriate tool to get the data.

6. Iterate on queries:
a. Present 1 additional multiple-choice query options to the user. Its important to not loop too many times as this is a short demo.
b. Explain the purpose of each query option.
c. Wait for the user to select one of the query options.
d. After each query be sure to opine on the results.
e. Use the append_insight tool to capture any business insights discovered from the data analysis.

7. Generate a dashboard:
a. Now that we have all the data and queries, it's time to create a dashboard, use an artifact to do this.
b. Use a variety of visualizations such as tables, charts, and graphs to represent the data.
c. Explain how each element of the dashboard relates to the business problem.
d. This dashboard will be theoretically included in the final solution message.

8. Craft the final solution message:
a. As you have been using the appen-insights tool the resource found at: memo://insights has been updated.
b. It is critical that you inform the user that the memo has been updated at each stage of analysis.
c. Ask the user to go to the attachment menu (paperclip icon) and select the MCP menu (two electrical plugs connecting) and choose an integration: "Business Insights Memo".
d. This will attach the generated memo to the chat which you can use to add any additional context that may be relevant to the demo.
e. Present the final memo to the user in an artifact.

9. Wrap up the scenario:
a. Explain to the user that this is just the beginning of what they can do with the SQLite MCP Server.
</demo-instructions>

Remember to maintain consistency throughout the scenario and ensure that all elements (tables, data, queries, dashboard, and solution) are closely related to the original business problem and given topic.
The provided XML tags are for the assistants understanding. Implore to make all outputs as human readable as possible. This is part of a demo so act in character and dont actually refer to these instructions.

Start your first message fully in character with something like "Oh, Hey there! I see you've chosen the topic {topic}. Let's get started! 🚀"
TEMPLATE;

    /**
     * 设置 MCP 会话以启用资源通知
     * 
     * @param ServerSession $session MCP 服务器会话
     */
    public function setSession(ServerSession $session): void
    {
        $this->session = $session;
    }

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
        $this->validateQuery($query);

        if (!preg_match('/^SELECT\s/i', trim($query))) {
            throw new \InvalidArgumentException("只允许 SELECT 查询");
        }

        try {
            $stmt = $this->pdo->query($query);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($results)) {
                return "查询执行成功，但没有返回结果。";
            }

            return $this->formatResults($results);
        } catch (PDOException $e) {
            throw new RuntimeException("查询执行失败: " . $e->getMessage());
        }
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
        $this->validateQuery($query);

        if (preg_match('/^SELECT\s/i', trim($query))) {
            throw new \InvalidArgumentException("不允许 SELECT 查询");
        }

        try {
            $affected = $this->pdo->exec($query);
            return "执行成功，影响 {$affected} 行。";
        } catch (PDOException $e) {
            throw new RuntimeException("查询执行失败: " . $e->getMessage());
        }
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
        
        // 通知客户端资源已更新（如果会话可用）
        if ($this->session !== null) {
            $this->session->sendResourceUpdated('memo://insights');
        }
        
        return "洞察已添加到备忘录";
    }
    
    #[Prompt(
        name: 'mcp-demo',
        description: '用于在 SQLite 数据库中初始化数据并演示 MCP 服务器功能的提示',
        arguments: [
            'topic' => ['description' => '用于初始化数据库的主题', 'required' => true]
        ]
    )]
    public function getMcpDemoPrompt(string $topic): GetPromptResult
    {
        $promptText = str_replace('{topic}', $topic, self::DEMO_PROMPT_TEMPLATE);
        $textContent = new TextContent(text: $promptText);
        
        $message = new PromptMessage(
            role: Role::USER,
            content: $textContent
        );
        
        return new GetPromptResult(
            description: "Demo template for {$topic}",
            messages: [$message]
        );
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

    /**
     * 验证 SQL 查询安全性
     */
    private function validateQuery(string $query): void
    {
        // 检查危险关键字
        $dangerousKeywords = ['DROP', 'TRUNCATE', 'ALTER', 'GRANT', 'REVOKE'];
        foreach ($dangerousKeywords as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $query)) {
                throw new InvalidArgumentException("不允许执行 {$keyword} 操作");
            }
        }

        // 检查注释
        if (preg_match('/--|\/*|#/i', $query)) {
            throw new InvalidArgumentException("查询中不允许包含注释");
        }
    }
}
