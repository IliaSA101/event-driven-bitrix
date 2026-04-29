<?php

namespace App\Controllers;

use App\Services\OrderService;
use Exception;

/**
 * Контроллер для работы с заказами пользователей.
 * Слой HTTP: отвечает исключительно за прием параметров, валидацию базового уровня
 * и возврат стандартизированных JSON-ответов. Никакой бизнес-логики здесь быть не должно.
 */
class OrderController
{
    /**
     * @var OrderService Сервис бизнес-логики заказов
     */
    private OrderService $orderService;

    /**
     * Внедрение зависимостей (DI) через конструктор.
     * * @param OrderService $orderService
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Основной метод (Action) для получения списка заказов.
     * Эндпоинт: GET /api/orders?user_id={id}
     *
     * @return void
     */
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Проверка API ключа (Zero Trust)
        $expectedKey = getenv('MICROSERVICE_API_KEY') ?: 'SecretToken123';
        $providedKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

        if ($providedKey !== $expectedKey) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden: Invalid API Key']);
            return;
        }

        // Базовая валидация входящих параметров
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing user_id parameter']);
            return;
        }

        try {
            // Делегируем работу сервису
            $result = $this->orderService->getUserOrders($userId);
            
            // Возвращаем успешный ответ со статусом 200
            http_response_code(200);
            echo json_encode($result);
        } catch (Exception $e) {
            // В production-среде здесь должно быть логирование ошибки (например, через Monolog),
            // а пользователю отдается обезличенное сообщение, чтобы не "светить" структуру БД или стектрейс.
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
    }
}