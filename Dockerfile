FROM php:8.1-cli

# 安装基本依赖和必要的PHP扩展
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install \
    zip \
    && rm -rf /var/lib/apt/lists/*

# 安装Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 设置工作目录
WORKDIR /app

# 复制项目文件
COPY . .

# 安装依赖
RUN composer install --no-dev && \
    mkdir -p /app/runtime && \
    chmod -R 777 /app/runtime

# 设置环境变量
ENV PHP_MEMORY_LIMIT=1G \
    PHP_TIMEZONE=PRC

# 启动命令
ENTRYPOINT ["php", "bin/console"]
