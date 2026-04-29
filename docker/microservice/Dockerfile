FROM php:8.2-fpm

# Установка зависимостей для RabbitMQ и Redis
RUN apt-get update && apt-get install -y \
    librabbitmq-dev \
    libssh-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Установка PHP расширений
RUN docker-php-ext-install pdo_mysql sockets

# Установка PECL расширений (AMQP и Redis)
RUN pecl install amqp redis \
    && docker-php-ext-enable amqp redis pdo_mysql sockets

# Установка Composer для загрузки библиотек (например, php-amqplib)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
