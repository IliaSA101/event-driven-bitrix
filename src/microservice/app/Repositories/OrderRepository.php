<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий заказов.
 * Отвечает за инкапсуляцию SQL-запросов к таблице b_sale_order.
 * Этот репозиторий должен использовать SLAVE соединение для снижения нагрузки на Master.
 */
class OrderRepository
{
    /** @var PDO Подключение к базе данных (на чтение) */
    private PDO $db;

    /**
     * @param PDO $slaveDb
     */
    public function __construct(PDO $slaveDb)
    {
        $this->db = $slaveDb;
    }

    /**
     * Получает список заказов конкретного пользователя по убыванию ID.
     *
     * @param int $userId Идентификатор пользователя
     * @param int $limit Максимальное количество записей (по умолчанию 50)
     * @return array Массив ассоциативных массивов с данными заказов
     */
    public function getByUserId(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT ID, DATE_INSERT, PRICE, STATUS_ID 
            FROM b_sale_order 
            WHERE USER_ID = :user_id 
            ORDER BY ID DESC 
            LIMIT :limit
        ");
        
        // ОСОБЕННОСТЬ ЛОГИКИ:
        // Использование PDO::PARAM_INT критически важно при работе с LIMIT в prepared statements.
        // Если передать лимит как строку (что происходит по умолчанию, если не указать тип),
        // MySQL с включенным STRICT MODE выбросит синтаксическую ошибку "LIMIT '50'".
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}