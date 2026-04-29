<?php

namespace App\Repositories;

use PDO;

/**
 * Репозиторий задач на генерацию отчета.
 * Работает с таблицей report_tasks.
 * Все операции изменения (UPDATE) должны проходить через MASTER соединение.
 */
class ReportTaskRepository
{
    /** @var PDO Подключение к базе данных (на запись) */
    private PDO $db;

    /**
     * @param PDO $masterDb
     */
    public function __construct(PDO $masterDb)
    {
        $this->db = $masterDb;
    }

    /**
     * Обновляет статус задачи и опционально сохраняет ссылку на сгенерированный файл.
     *
     * @param int $taskId Идентификатор задачи
     * @param string $status Новый статус ('pending', 'processing', 'done', 'error')
     * @param string|null $fileUrl URL файла для скачивания (передается только при статусе 'done')
     * @return void
     */
    public function updateStatus(int $taskId, string $status, ?string $fileUrl = null): void
    {
        // Динамическое построение запроса: обновляем file_url только если он передан.
        // Это позволяет одним методом менять разные статусы, не затирая поле с файлом.
        $sql = "UPDATE report_tasks SET status = :status";
        $params = [
            'id' => $taskId,
            'status' => $status
        ];

        if ($fileUrl !== null) {
            $sql .= ", file_url = :file_url";
            $params['file_url'] = $fileUrl;
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}