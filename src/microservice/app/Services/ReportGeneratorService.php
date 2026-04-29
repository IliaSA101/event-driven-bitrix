<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\ReportTaskRepository;
use Exception;

/**
 * Сервис генерации отчетов.
 * Вызывается исключительно в фоновом режиме (CLI воркером), так как процесс может быть долгим.
 */
class ReportGeneratorService
{
    private OrderRepository $orderRepository;
    private ReportTaskRepository $taskRepository;

    /**
     * @param OrderRepository $orderRepository Репозиторий для получения данных
     * @param ReportTaskRepository $taskRepository Репозиторий для обновления статуса задачи
     */
    public function __construct(OrderRepository $orderRepository, ReportTaskRepository $taskRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->taskRepository = $taskRepository;
    }

    /**
     * Основной метод генерации отчета.
     * Формирует CSV файл, работает с файловой системой и управляет жизненным циклом задачи.
     *
     * @param int $taskId Идентификатор задачи на генерацию отчета
     * @param int $userId Идентификатор пользователя, для которого строится отчет
     * @return void
     */
    public function generate(int $taskId, int $userId): void
    {
        try {
            // Переводим задачу в статус "в обработке", чтобы избежать дублирования
            // если вдруг сообщение из очереди прочитают несколько воркеров
            $this->taskRepository->updateStatus($taskId, 'processing');
            
            // Выбираем данные для отчета из Slave БД.
            // Используем увеличенный лимит, так как для отчета нужна полная история, а не постраничная
            $orders = $this->orderRepository->getByUserId($userId, 10000);
            
            // Имитация долгой ресурсоемкой работы (CPU/IO Bound)
            sleep(5);
            
            // --- Работа с подпапкой ---
            // В реальном проекте базовый путь берется из конфигурации.
            $reportsDir = "/var/www/html/upload/reports";
            
            // Проверяем существование папки, если нет - создаем
            if (!is_dir($reportsDir)) {
                // Права 0775 позволяют записывать файлы группе пользователей веб-сервера, 
                // флаг true создает вложенные директории рекурсивно.
                if (!mkdir($reportsDir, 0775, true)) {
                    throw new Exception("Не удалось создать директорию: {$reportsDir}");
                }
            }

            // Формируем уникальное имя файла, защищенное от перезаписи
            $fileName = "report_{$taskId}_" . time() . ".csv";
            $filePath = $reportsDir . "/" . $fileName; 
            
            // Формирование файла (Stream запись для экономии RAM, если данных много)
            $file = fopen($filePath, 'w');
            if ($file === false) {
                throw new Exception("Не удалось открыть файл для записи: {$filePath}");
            }

            // Пишем заголовки и данные
            fputcsv($file, ['ID', 'DATE', 'PRICE', 'STATUS'], ';');
            foreach ($orders as $order) {
                fputcsv($file, [$order['ID'], $order['DATE_INSERT'], $order['PRICE'], $order['STATUS_ID']], ';');
            }
            fclose($file);
            
            // Относительная ссылка для скачивания файла через фронтенд (с учетом подпапки)
            $fileUrl = "/upload/reports/" . $fileName;
            
            // Обновляем статус задачи в Master БД: успешно завершена + прикладываем ссылку
            $this->taskRepository->updateStatus($taskId, 'done', $fileUrl);
            
        } catch (Exception $e) {
            // В случае любой ошибки обязательно переводим статус в ошибку,
            // иначе фронтенд застрянет в вечном поллинге
            $this->taskRepository->updateStatus($taskId, 'error');
            
            // Вывод в stdout (который перехватит Docker logs)
            echo " [!] Error generating report for Task {$taskId}: " . $e->getMessage() . "\n";
        }
    }
}