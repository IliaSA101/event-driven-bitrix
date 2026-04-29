<?php

namespace App\Orders\RabbitMQ;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Config\Option;
use Exception;

/**
 * Класс для публикации сообщений в RabbitMQ.
 * * ВНИМАНИЕ (Архитектурное решение):
 * Так как установка php-amqplib внутри ядра Битрикс через Composer 
 * может вызвать конфликты зависимостей, мы используем HTTP API RabbitMQ (порт 15672)
 * для публикации сообщений. Это легкий и безопасный способ интеграции из монолита.
 */
class Publisher
{
    /**
     * Публикует сообщение в Exchange (Topic)
     */
    public static function publish(string $exchange, string $routingKey, array $payload): bool
    {
        $moduleId = 'app.orders';
        
        // Получаем настройки из опций модуля с fallback-значениями
        $host = Option::get($moduleId, 'rabbitmq_host', 'rabbitmq');
        $port = Option::get($moduleId, 'rabbitmq_port', '15672');
        $user = Option::get($moduleId, 'rabbitmq_user', 'guest');
        $pass = Option::get($moduleId, 'rabbitmq_password', 'guest');

        $url = "http://" . $host . ":" . $port . "/api/exchanges/%2F/{$exchange}/publish";
        
        // Формат payload для HTTP API RabbitMQ
        $data = [
            'properties' => ['delivery_mode' => 2], // 2 = persistent message
            'routing_key' => $routingKey,
            'payload' => json_encode($payload),
            'payload_encoding' => 'string'
        ];

        $httpClient = new HttpClient();
        $httpClient->setHeader('Content-Type', 'application/json');
        $httpClient->setAuthorization($user, $pass);

        try {
            $response = $httpClient->post($url, json_encode($data));
            $result = json_decode($response, true);
            return isset($result['routed']) && $result['routed'] === true;
        } catch (Exception $e) {
            // В продакшене писать в лог
            return false;
        }
    }
}