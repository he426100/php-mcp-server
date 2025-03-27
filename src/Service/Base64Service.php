<?php

declare(strict_types=1);

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;
use Mcp\Types\ImageContent;

class Base64Service
{
    /**
     * Base64编码
     */
    #[Tool(
        name: 'encode',
        description: '将文本转换为Base64编码',
        parameters: [
            'text' => [
                'type' => 'string',
                'description' => '要编码的文本',
                'required' => true
            ]
        ]
    )]
    public function encode(string $text): string
    {
        return base64_encode($text);
    }

    /**
     * Base64解码
     */
    #[Tool(
        name: 'decode',
        description: '将Base64编码转换为文本',
        parameters: [
            'base64' => [
                'type' => 'string',
                'description' => '要解码的Base64字符串',
                'required' => true
            ]
        ]
    )]
    public function decode(string $base64): string
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('无效的Base64编码');
        }
        return $decoded;
    }

    /**
     * Base64转图片
     */
    #[Tool(
        name: 'to-image',
        description: '将Base64编码转换为图片',
        parameters: [
            'base64' => [
                'type' => 'string',
                'description' => '图片的Base64编码字符串(可以包含data URI scheme前缀)',
                'required' => true
            ]
        ]
    )]
    public function toImage(string $base64): ImageContent
    {
        // 移除data URI scheme前缀
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $base64 = preg_replace('/^data:image\/(\w+);base64,/', '', $base64);
        }

        // 解码Base64
        $imageData = base64_decode($base64, true);
        if ($imageData === false) {
            throw new \InvalidArgumentException('无效的Base64编码');
        }

        // 检测图片类型
        $f = finfo_open();
        $mimeType = finfo_buffer($f, $imageData, FILEINFO_MIME_TYPE);
        finfo_close($f);

        if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
            throw new \InvalidArgumentException('无效的图片数据');
        }

        return new ImageContent($base64, $mimeType);
    }
}
