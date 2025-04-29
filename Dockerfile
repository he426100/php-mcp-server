FROM php:8.1-cli

ARG SW_VERSION

ENV SW_VERSION=${SW_VERSION:-"develop"}

# 安装基本依赖和必要的PHP扩展
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install \
    zip \
    sockets \
    # 下载并安装 swow 扩展
    && cd /tmp \
    && curl -SL "https://github.com/swow/swow/archive/${SW_VERSION}.tar.gz" -o swow.tar.gz \
    && mkdir -p swow \
    && tar -xf swow.tar.gz -C swow --strip-components=1 \
    && ( \
        cd swow/ext \
        && phpize \
        && ./configure --enable-swow --enable-swow-ssl --enable-swow-curl \
        && make -s -j$(nproc) && make install \
    ) \
    # 配置 PHP
    && echo "extension=swow.so" > /usr/local/etc/php/conf.d/swow.ini \
    && echo "memory_limit=1G" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "max_input_vars=PHP_INT_MAX" >> /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    # 清理
    && rm -rf /tmp/* \
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
