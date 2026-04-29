FROM php:8.2-fpm

# Установка необходимых системных пакетов
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Конфигурация и установка расширений PHP для Bitrix
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo_mysql opcache zip

# Базовые настройки PHP для Bitrix
RUN echo "short_open_tag = On" > /usr/local/etc/php/conf.d/bitrix.ini \
    && echo "max_input_vars = 10000" >> /usr/local/etc/php/conf.d/bitrix.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/bitrix.ini \
    && echo "date.timezone = Europe/Moscow" >> /usr/local/etc/php/conf.d/bitrix.ini

# Золотой стандарт настроек OPcache для 1C-Bitrix
RUN echo "opcache.enable = 1" >> /usr/local/etc/php/conf.d/opcache-bitrix.ini \
    && echo "opcache.memory_consumption = 256" >> /usr/local/etc/php/conf.d/opcache-bitrix.ini \
    && echo "opcache.interned_strings_buffer = 24" >> /usr/local/etc/php/conf.d/opcache-bitrix.ini \
    && echo "opcache.max_accelerated_files = 100000" >> /usr/local/etc/php/conf.d/opcache-bitrix.ini \
    && echo "opcache.revalidate_freq = 0" >> /usr/local/etc/php/conf.d/opcache-bitrix.ini \
    && echo "opcache.save_comments = 1" >> /usr/local/etc/php/conf.d/opcache-bitrix.ini \
    && echo "opcache.fast_shutdown = 1" >> /usr/local/etc/php/conf.d/opcache-bitrix.ini