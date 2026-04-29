<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repositories\OrderRepository;
use App\Services\OrderService;
use App\Controllers\OrderController;

// 1. Инициализация соединений через переменные окружения
$redisHost = getenv('REDIS_HOST') ?: 'redis';
$redisPort = (int)(getenv('REDIS_PORT') ?: 6379);

$redis = new Redis();
$redis->connect($redisHost, $redisPort);

$dbHost = getenv('DB_HOST_SLAVE') ?: 'db-slave';
$dbName = getenv('DB_DATABASE') ?: 'bitrix';
$dbUser = getenv('DB_USER') ?: 'bitrix';
$dbPass = getenv('DB_PASSWORD') ?: '123';

$slaveDb = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8", $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false // Важно для корректных типов при LIMIT
]);

// 2. Сборка графа зависимостей (DI)
$orderRepository = new OrderRepository($slaveDb);
$orderService = new OrderService($orderRepository, $redis);
$orderController = new OrderController($orderService);

// 3. Маршрутизация
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/api/orders') {
    $orderController->index();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}