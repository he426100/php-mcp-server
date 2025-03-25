<?php

namespace He426100\McpServer\Service;

use He426100\McpServer\Annotation\McpHandler;
use Mcp\Server\Server;
use Mcp\Types\CallToolResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\TextContent;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputProperties;
use Mcp\Types\ToolInputSchema;
use ReflectionClass;
use ReflectionMethod;

class WeidianBarcodeService extends AbstractMcpService
{
    /**
     * 列出可用工具
     *
     * @param mixed $params
     * @return ListToolsResult
     */
    #[McpHandler('tools/list')]
    public function listTools($params): ListToolsResult
    {
        // 创建工具输入属性
        $properties = ToolInputProperties::fromArray([
            'barcode' => [
                'type' => 'string',
                'description' => '商品条码'
            ]
        ]);

        // 创建输入模式
        $inputSchema = new ToolInputSchema(
            properties: $properties,
            required: ['barcode']
        );

        // 创建工具定义
        $tool = new Tool(
            name: 'query-barcode',
            description: '查询微店商品条码信息',
            inputSchema: $inputSchema
        );

        return new ListToolsResult([$tool]);
    }

    /**
     * 调用指定工具
     *
     * @param mixed $params
     * @return CallToolResult
     */
    #[McpHandler('tools/call')]
    public function callTool($params): CallToolResult
    {
        $name = $params->name;
        $arguments = $params->arguments ?? [];

        if ($name !== 'query-barcode') {
            throw new \InvalidArgumentException("未知的工具: {$name}");
        }

        // 获取条码参数
        $barcode = $arguments['barcode'] ?? null;
        if (!$barcode) {
            return new CallToolResult(
                content: [new TextContent(
                    text: "错误: 缺少必需的条码参数"
                )],
                isError: true
            );
        }
        $barcode = trim($barcode);

        // 获取环境变量中的 cookies
        $cookies = getenv('WEIDIAN_COOKIES');
        if (!$cookies) {
            return new CallToolResult(
                content: [new TextContent(
                    text: "错误: 未设置 WEIDIAN_COOKIES 环境变量"
                )],
                isError: true
            );
        }

        try {
            // 查询条码信息
            $result = $this->queryBarcode($barcode, $cookies);

            // 准备返回内容
            $contents = [];

            // 添加文本内容
            $lines = [
                "商品信息:",
                "----------------------------------------",
                sprintf("条码: %s", $result['barcode'] ?? '未知'),
                sprintf("商品名称: %s", $result['itemName'] ?? '未知'),
                sprintf("价格: ￥%s", $result['price'] ?? '未知'),
                sprintf("销量: %s", $result['sold'] ?? '0'),
                sprintf("规格: %s", $result['spec'] ?: '默认'),
            ];

            // 添加建议价格信息
            if (!empty($result['suggestPrice'])) {
                $lines[] = "\n建议价格:";
                $lines[] = "----------------------------------------";
                foreach ($result['suggestPrice'] as $price) {
                    $percentage = round(floatval($price['rate']) * 100, 1);
                    $lines[] = sprintf("￥%s (占比 %s%%)", $price['price'], $percentage);
                }
            }

            // 添加图片内容
            if (!empty($result['imgHead'])) {
                $lines[] = "\n商品图片:";
                $lines[] = "----------------------------------------";
                $lines[] = sprintf("图片：%s", $result['imgHead']);
            }

            $contents[] = new TextContent(
                text: implode("\n", $lines)
            );

            return new CallToolResult(
                content: $contents
            );
        } catch (\Exception $e) {
            // $logger->error($e->getMessage());
            // $logger->debug('cookies: ' . $cookies);
            return new CallToolResult(
                content: [new TextContent(
                    text: "错误: " . $e->getMessage()
                )],
                isError: true
            );
        }
    }

    /**
     * 查询条码信息
     *
     * @param string $barcode 条码
     * @param string $cookies Cookie信息
     * @return array 查询结果
     * @throws \RuntimeException 当请求失败时抛出
     */
    protected function queryBarcode(string $barcode, string $cookies): array
    {
        $url = sprintf(
            "https://thor.weidian.com/retailcore/standardItem.query/1.0?param=%%7B%%22barcode%%22%%3A%%22%s%%22%%2C%%22operateType%%22%%3A%%22create%%22%%7D&wdtoken=04df25a2&_=%d",
            $barcode,
            time() * 1000
        );

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Linux; Android 10; VOG-AL00 Build/HUAWEIVOG-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/132.0.6834.163 Mobile Safari/537.36 KDJSBridge2/1.1.0 platform/android WDAPP(WD/9.5.50)',
                'Accept: application/json, */*',
                'Origin: https://h5.weidian.com',
                'X-Requested-With: com.weidian.smartstore',
                'Referer: https://h5.weidian.com/',
                'Cookie: ' . $cookies
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("HTTP请求失败: $httpCode");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('解析JSON响应失败');
        }

        if (!isset($data['result'])) {
            throw new \RuntimeException('接口返回数据格式错误: ' . $response);
        }

        return $data['result'];
    }
} 