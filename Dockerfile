FROM registry.cn-hangzhou.aliyuncs.com/jcleng/library-alpine:3.20.1

RUN apk add --no-cache curl tar \
    && curl -fsSL https://dl.static-php.dev/static-php-cli/common/php-8.1.23-cli-linux-x86_64.tar.gz | tar -xz -C /usr/local/bin \
    && rm -rf /var/cache/apk/*

WORKDIR /app
COPY . .

EXPOSE 50116

CMD ["sh", "-c", "PHP_CLI_SERVER_WORKERS=20 php -S 0.0.0.0:50116 router.php"]
