<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use Redis;

/**
 * Сервис для бизнес-логики работы с заказами.
 * Реализует паттерн кэширования "Cache-Aside" (Ленивая загрузка).
 */
class OrderService
{
    /** @var int Время жизни кэша списка заказов (в секундах) согласно ТЗ */
    private const CACHE_TTL = 60;
    
    /** @var string Префикс ключа в Redis для избежания коллизий */
    private const CACHE_PREFIX = 'orders:user:';

    private OrderRepository $orderRepository;
    private Redis $redis;

    /**
     * @param OrderRepository $orderRepository Слой доступа к БД
     * @param Redis $redis Клиент Redis для кэширования
     */
    public function __construct(OrderRepository $orderRepository, Redis $redis)
    {
        $this->orderRepository = $orderRepository;
        $this->redis = $redis;
    }

    /**
     * Получает список заказов пользователя.
     * Сначала проверяет горячий кэш (Redis). При промахе (Cache Miss) 
     * обращается к БД (Slave) и прогревает кэш.
     *
     * @param int $userId Идентификатор пользователя
     * @return array Массив, содержащий источник данных ('redis' или 'database') и сами данные
     */
    public function getUserOrders(int $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . $userId;
        
        $cached = $this->redis->get($cacheKey);
        
        // Cache Hit: данные найдены в кэше
        if ($cached) {
            return [
                'source' => 'redis',
                'data' => json_decode($cached, true)
            ];
        }

        // Cache Miss: идем в базу данных (Слейв)
        $orders = $this->orderRepository->getByUserId($userId);
        
        // Сохраняем в кэш с ограничением времени жизни (TTL)
        // json_encode используется, так как Redis хранит строки
        $this->redis->setex($cacheKey, self::CACHE_TTL, json_encode($orders));

        return [
            'source' => 'database',
            'data' => $orders
        ];
    }

    /**
     * Инвалидирует (сбрасывает) кэш пользователя.
     * Вызывается асинхронным воркером при получении события о новом заказе из RabbitMQ.
     *
     * @param int $userId Идентификатор пользователя
     * @return void
     */
    public function invalidateCache(int $userId): void
    {
        $cacheKey = self::CACHE_PREFIX . $userId;
        $this->redis->del($cacheKey);
    }
}