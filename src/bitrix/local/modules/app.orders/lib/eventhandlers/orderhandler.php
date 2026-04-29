<?php

namespace App\Orders\EventHandlers;

use Bitrix\Main\Event;
use App\Orders\RabbitMQ\Publisher;

/**
 * Обработчик D7 событий модуля Sale (Интернет-магазин)
 */
class OrderHandler
{
    /**
     * Метод вызывается при любом сохранении заказа (создание или обновление).
     * Отправляет событие в RabbitMQ для инвалидации кэша микросервиса.
     * * @param Event $event
     */
    public static function onOrderSaved(Event $event)
    {
        $order = $event->getParameter("ENTITY");
        $isNew = $event->getParameter("IS_NEW");
        
        $userId = $order->getUserId();

        // Мы инвалидируем кэш только при создании НОВОГО заказа (согласно логике ТЗ)
        // Но при желании можно убрать проверку $isNew, чтобы сбрасывать кэш и при смене статуса
        if ($isNew && $userId > 0) {
            
            // Публикуем событие "order.created"
            Publisher::publish(
                'orders_exchange',
                'order.created',
                [
                    'user_id' => $userId,
                    'order_id' => $order->getId(),
                    'timestamp' => time()
                ]
            );
        }
    }
}