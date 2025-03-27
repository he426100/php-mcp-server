<?php

declare(strict_types=1);

namespace He426100\McpServer\Service;

use Psr\Log\LoggerInterface;

class BaseService
{
    public function __construct(private ?LoggerInterface $logger = null) {}
}
