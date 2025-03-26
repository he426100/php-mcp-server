<?php

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;

class JsonConverterService
{
    /**
     * 将JSON转换为查询字符串
     */
    #[Tool(
        name: 'json-to-query',
        description: '将JSON对象转换为URL查询字符串',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ]
        ]
    )]
    public function jsonToQuery(string $json): string
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('无效的JSON格式: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('JSON必须是一个对象或数组');
        }

        return $this->buildQuery($data);
    }

    /**
     * 将查询字符串转换为JSON
     */
    #[Tool(
        name: 'query-to-json',
        description: '将URL查询字符串转换为JSON对象',
        parameters: [
            'query' => [
                'type' => 'string',
                'description' => 'URL查询字符串(不含?前缀)',
                'required' => true
            ]
        ]
    )]
    public function queryToJson(string $query): string
    {
        // 移除可能存在的问号前缀
        if (strpos($query, '?') === 0) {
            $query = substr($query, 1);
        }

        $params = [];
        parse_str($query, $params);

        return json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 将JSON转换为PHP数组表示
     */
    #[Tool(
        name: 'json-to-php-array',
        description: '将JSON转换为PHP数组表示字符串',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ],
        ]
    )]
    public function jsonToPhpArray(string $json): string
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('无效的JSON格式: ' . json_last_error_msg());
        }

        $exported = var_export($data, true);

        // 格式化输出，将array ( 替换为 array(
        $exported = preg_replace('/array \(/', 'array(', $exported);
        // 减少多余的空格
        $exported = preg_replace('/=>\s+/', '=> ', $exported);

        return $exported;
    }

    /**
     * 格式化JSON字符串
     */
    #[Tool(
        name: 'format-json',
        description: '美化JSON格式，使其更易读',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ],
        ]
    )]
    public function formatJson(string $json): string
    {
        $data = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('无效的JSON格式: ' . json_last_error_msg());
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 压缩JSON字符串
     */
    #[Tool(
        name: 'minify-json',
        description: '压缩JSON字符串，移除不必要的空白',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ]
        ]
    )]
    public function minifyJson(string $json): string
    {
        $data = json_decode($json);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('无效的JSON格式: ' . json_last_error_msg());
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 验证JSON字符串格式
     */
    #[Tool(
        name: 'validate-json',
        description: '验证JSON字符串是否格式正确',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ]
        ]
    )]
    public function validateJson(string $json): string
    {
        json_decode($json);
        $error = json_last_error();

        if ($error !== JSON_ERROR_NONE) {
            return "JSON格式无效: " . json_last_error_msg();
        }

        return "JSON格式有效";
    }

    /**
     * 将JSON转换为XML
     */
    #[Tool(
        name: 'json-to-xml',
        description: '将JSON转换为XML格式',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ],
            'rootElement' => [
                'type' => 'string',
                'description' => 'XML根元素名称',
                'required' => false
            ]
        ]
    )]
    public function jsonToXml(string $json, string $rootElement = 'root'): string
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('无效的JSON格式: ' . json_last_error_msg());
        }

        $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><{$rootElement}></{$rootElement}>");

        $this->arrayToXml($data, $xml);

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /**
     * 将XML转换为JSON
     */
    #[Tool(
        name: 'xml-to-json',
        description: '将XML转换为JSON格式',
        parameters: [
            'xml' => [
                'type' => 'string',
                'description' => 'XML字符串',
                'required' => true
            ]
        ]
    )]
    public function xmlToJson(string $xml): string
    {
        try {
            $xml = trim($xml);
            $simpleXml = new \SimpleXMLElement($xml);
            $json = json_encode($this->xmlToArray($simpleXml), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                throw new \Exception('XML转换JSON失败: ' . json_last_error_msg());
            }

            return $json;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('XML解析错误: ' . $e->getMessage());
        }
    }

    /**
     * 构建查询字符串
     *
     * @param array $data 要转换的数据
     * @param string $prefix 键前缀
     * @return string 查询字符串
     */
    private function buildQuery(array $data, string $prefix = ''): string
    {
        $params = [];

        foreach ($data as $key => $value) {
            $keyPath = $prefix ? $prefix . '[' . $key . ']' : $key;

            if (is_array($value)) {
                $params[] = $this->buildQuery($value, $keyPath);
            } else {
                $params[] = urlencode($keyPath) . '=' . urlencode($value);
            }
        }

        return implode('&', $params);
    }

    /**
     * 将数组转换为XML
     *
     * @param array $data 要转换的数组
     * @param \SimpleXMLElement $xml XML对象
     */
    private function arrayToXml(array $data, \SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // 处理数字索引数组
                if (is_numeric($key)) {
                    $key = "item" . $key;
                }

                // 创建新元素
                $subNode = $xml->addChild($key);
                $this->arrayToXml($value, $subNode);
            } else {
                // 处理数字索引
                if (is_numeric($key)) {
                    $key = "item" . $key;
                }

                // 添加值，处理特殊字符
                $xml->addChild($key, htmlspecialchars((string)$value, ENT_XML1));
            }
        }
    }

    /**
     * 将XML转换为数组
     *
     * @param \SimpleXMLElement $xml XML对象
     * @return array 转换后的数组
     */
    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $array = [];

        foreach ($xml->children() as $child) {
            $childName = $child->getName();

            if ($child->count() > 0) {
                // 递归处理子元素
                $value = $this->xmlToArray($child);
            } else {
                $value = (string)$child;
            }

            // 处理同名节点
            if (isset($array[$childName])) {
                if (!is_array($array[$childName]) || !isset($array[$childName][0])) {
                    $array[$childName] = [$array[$childName]];
                }
                $array[$childName][] = $value;
            } else {
                $array[$childName] = $value;
            }
        }

        // 处理属性
        foreach ($xml->attributes() as $name => $value) {
            $array['@' . $name] = (string)$value;
        }

        return $array;
    }

    /**
     * 对JSON对象的键进行排序
     */
    #[Tool(
        name: 'sort-json',
        description: '对JSON对象中的键进行排序',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ],
            'sortOrder' => [
                'type' => 'string',
                'description' => '排序方式: asc(升序，默认), desc(降序)',
                'required' => false
            ],
            'recursive' => [
                'type' => 'boolean',
                'description' => '是否递归排序嵌套对象，默认为true',
                'required' => false
            ]
        ]
    )]
    public function sortJson(string $json, string $sortOrder = 'asc', bool $recursive = true): string
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('无效的JSON格式: ' . json_last_error_msg());
        }

        $sortedData = $this->sortArray($data, $sortOrder, $recursive);
        
        return json_encode($sortedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 合并两个JSON对象
     */
    #[Tool(
        name: 'merge-json',
        description: '将两个JSON对象合并为一个',
        parameters: [
            'json1' => [
                'type' => 'string',
                'description' => '第一个JSON字符串',
                'required' => true
            ],
            'json2' => [
                'type' => 'string',
                'description' => '第二个JSON字符串',
                'required' => true
            ],
            'overwrite' => [
                'type' => 'boolean',
                'description' => '冲突时是否用第二个JSON的值覆盖第一个，默认为true',
                'required' => false
            ]
        ]
    )]
    public function mergeJson(string $json1, string $json2, bool $overwrite = true): string
    {
        $data1 = json_decode($json1, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('第一个JSON格式无效: ' . json_last_error_msg());
        }
        
        $data2 = json_decode($json2, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('第二个JSON格式无效: ' . json_last_error_msg());
        }
        
        if (!is_array($data1) || !is_array($data2)) {
            throw new \InvalidArgumentException('两个JSON必须都是对象或数组');
        }
        
        $result = $overwrite 
            ? array_replace_recursive($data1, $data2)
            : array_merge_recursive($data1, $data2);
        
        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 从JSON中提取特定路径的值
     */
    #[Tool(
        name: 'extract-json-path',
        description: '从JSON中提取特定路径的值',
        parameters: [
            'json' => [
                'type' => 'string',
                'description' => 'JSON字符串',
                'required' => true
            ],
            'path' => [
                'type' => 'string',
                'description' => '要提取的路径，使用点号分隔，例如 "user.address.city"',
                'required' => true
            ]
        ]
    )]
    public function extractJsonPath(string $json, string $path): string
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('无效的JSON格式: ' . json_last_error_msg());
        }
        
        $segments = explode('.', $path);
        $current = $data;
        
        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                throw new \InvalidArgumentException("路径 '{$path}' 不存在");
            }
            $current = $current[$segment];
        }
        
        if (is_array($current)) {
            return json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        return (string)$current;
    }

    /**
     * 比较两个JSON的差异
     */
    #[Tool(
        name: 'diff-json',
        description: '比较两个JSON对象的差异',
        parameters: [
            'json1' => [
                'type' => 'string',
                'description' => '第一个JSON字符串',
                'required' => true
            ],
            'json2' => [
                'type' => 'string',
                'description' => '第二个JSON字符串',
                'required' => true
            ]
        ]
    )]
    public function diffJson(string $json1, string $json2): string
    {
        $data1 = json_decode($json1, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('第一个JSON格式无效: ' . json_last_error_msg());
        }
        
        $data2 = json_decode($json2, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('第二个JSON格式无效: ' . json_last_error_msg());
        }
        
        $diff = $this->arrayDiff($data1, $data2);
        
        return json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 递归对数组进行排序
     */
    private function sortArray(array $array, string $sortOrder = 'asc', bool $recursive = true): array
    {
        if ($recursive) {
            foreach ($array as &$value) {
                if (is_array($value)) {
                    $value = $this->sortArray($value, $sortOrder, $recursive);
                }
            }
        }
        
        if ($sortOrder === 'desc') {
            krsort($array);
        } else {
            ksort($array);
        }
        
        return $array;
    }

    /**
     * 递归比较两个数组的差异
     */
    private function arrayDiff(array $array1, array $array2): array
    {
        $difference = [];
        
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                $difference[$key] = [
                    'status' => 'removed',
                    'value' => $value
                ];
                continue;
            }
            
            if (is_array($value) && is_array($array2[$key])) {
                $subDiff = $this->arrayDiff($value, $array2[$key]);
                if (!empty($subDiff)) {
                    $difference[$key] = [
                        'status' => 'modified',
                        'diff' => $subDiff
                    ];
                }
            } elseif ($value !== $array2[$key]) {
                $difference[$key] = [
                    'status' => 'modified',
                    'old' => $value,
                    'new' => $array2[$key]
                ];
            }
        }
        
        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key, $array1)) {
                $difference[$key] = [
                    'status' => 'added',
                    'value' => $value
                ];
            }
        }
        
        return $difference;
    }
}
