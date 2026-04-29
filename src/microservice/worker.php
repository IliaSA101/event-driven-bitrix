<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Repositories\OrderRepository;
use App\Repositories\ReportTaskRepository;
use App\Services\OrderService;
use App\Services\ReportGeneratorService;
use PhpAmqpLib\Connection\AMQPStreamConnection;

echo " [*] Worker is starting (ENV Configured Edition)...\n";

// 1. Инициализация соединений через переменные окружения
$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = (int)(getenv('REDIS_PORT') ?: 6379);

$redis = new Redis();
$redis->connect($redisHost, $redisPort);

// Настройки БД
$dbName = getenv('DB_DATABASE') ?: 'bitrix';
$dbUser = getenv('DB_USER') ?: 'bitrix';
$dbPass = getenv('DB_PASSWORD') ?: '123';

$masterDb = new PDO("mysql:host=" . (getenv('DB_HOST_MASTER') ?: 'db-master') . ";dbname={$dbName};charset=utf8", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$slaveDb = new PDO("mysql:host=" . (getenv('DB_HOST_SLAVE') ?: 'db-slave') . ";dbname={$dbName};charset=utf8", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false
]);

// Настройки RabbitMQ
$rmqHost = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
$rmqPort = getenv('RABBITMQ_PORT') ?: 5672;
$rmqUser = getenv('RABBITMQ_USER') ?: 'guest';
$rmqPass = getenv('RABBITMQ_PASSWORD') ?: 'guest';

$rabbitmq = new AMQPStreamConnection($rmqHost, $rmqPort, $rmqUser, $rmqPass);
$channel = $rabbitmq->channel();

// 2. Сборка графа зависимостей (DI)
$orderRepo = new OrderRepository($slaveDb);
$taskRepo = new ReportTaskRepository($masterDb);

$orderService = new OrderService($orderRepo, $redis);
$reportService = new ReportGeneratorService($orderRepo, $taskRepo);

// 3. Настройка очередей RabbitMQ
$channel->exchange_declare('orders_exchange', 'topic', false, true, false);

$channel->queue_declare('cache_invalidation_queue', false, true, false, false);
$channel->queue_bind('cache_invalidation_queue', 'orders_exchange', 'order.created');

$channel->queue_declare('report_generation_queue', false, true, false, false);
$channel->queue_bind('report_generation_queue', 'orders_exchange', 'report.generate');

echo " [*] Waiting for messages. To exit press CTRL+C\n";

// 4. Подписка на события
$channel->basic_consume('cache_invalidation_queue', '', false, false, false, false, function ($msg) use ($orderService) {
    $data = json_decode($msg->body, true);
    $userId = $data['user_id'] ?? 0;
    
    if ($userId) {
        $orderService->invalidateCache($userId);
        echo " [x] Cache invalidated for User ID: {$userId}\n";
    }
    $msg->ack();
});

$channel->basic_consume('report_generation_queue', '', false, false, false, false, function ($msg) use ($reportService) {
    $data = json_decode($msg->body, true);
    $taskId = $data['task_id'] ?? 0;
    $userId = $data['user_id'] ?? 0;
    
    if ($taskId && $userId) {
        echo " [x] Generating report Task ID: {$taskId} for User ID: {$userId}...\n";
        $reportService->generate($taskId, $userId);
        echo " [v] Report generation finished for Task ID: {$taskId}\n";
    }
    $msg->ack();
});

// Слушаем канал
while ($channel->is_open()) {
    $channel->wait();
}

$channel->close();
$rabbitmq->close();