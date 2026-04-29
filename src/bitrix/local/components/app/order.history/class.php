<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use App\Orders\RabbitMQ\Publisher;
use App\Orders\Orm\ReportTaskTable;

/**
 * Класс компонента выступает в роли API Gateway для фронтенда (Vue).
 * Отвечает за маршрутизацию REST-запросов и работу с локальной БД через ORM.
 */
class OrderHistoryComponent extends CBitrixComponent implements Controllerable
{
    public function configureActions(): array
    {
        return [
            'getOrders' => [
                'prefilters' => [new ActionFilter\Authentication()]
            ],
            'generateReport' => [
                'prefilters' => [new ActionFilter\Authentication()]
            ],
            'checkReportStatus' => [
                'prefilters' => [new ActionFilter\Authentication()]
            ],
            'downloadReport' => [
                'prefilters' => [new ActionFilter\Authentication()]
            ]
        ];
    }

    /**
     * AJAX Action: Получить список заказов.
     */
    public function getOrdersAction(): array
    {
        global $USER;
        $userId = (int)$USER->GetID();

        $httpClient = new HttpClient();
        
        $apiKey = Option::get('app.orders', 'api_key', 'SecretToken123');
        $httpClient->setHeader('X-API-Key', $apiKey);

        $response = $httpClient->get("http://microservice-api:8000/api/orders?user_id=" . $userId);

        if ($response === false) {
            $errors = $httpClient->getError();
            return ['error' => 'Микросервис недоступен. Ошибки: ' . implode(', ', $errors)];
        }

        $decoded = json_decode($response, true);
        
        if ($decoded === null) {
            return ['error' => 'Ошибка парсинга ответа от API. Ответ сервера: ' . strip_tags($response)];
        }

        return $decoded;
    }

    /**
     * AJAX Action: Запросить генерацию отчета.
     */
    public function generateReportAction(): array
    {
        global $USER;
        $userId = (int)$USER->GetID();
        
        Loader::includeModule('app.orders');

        // Rate Limiting через ORM
        $activeTask = ReportTaskTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=USER_ID' => $userId,
                // Истинный стандарт D7 для IN (...), вместо устаревшего '@'
                '=STATUS' => ['pending', 'processing'] 
            ],
            'limit' => 1
        ])->fetch();

        if ($activeTask) {
            return [
                'error' => true,
                'message' => 'У вас уже есть формирующийся отчет. Пожалуйста, дождитесь его завершения.'
            ];
        }

        // Создаем запись через ORM
        $result = ReportTaskTable::add([
            'USER_ID' => $userId,
            'STATUS' => 'pending'
        ]);

        if (!$result->isSuccess()) {
            return ['error' => true, 'message' => implode(', ', $result->getErrorMessages())];
        }

        $taskId = $result->getId();

        // Отправляем задачу в RabbitMQ
        $isPublished = Publisher::publish('orders_exchange', 'report.generate', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'timestamp' => time()
        ]);

        return [
            'task_id' => $taskId,
            'published' => $isPublished
        ];
    }

    /**
     * AJAX Action: Проверить статус задачи (Поллинг).
     */
    public function checkReportStatusAction(int $taskId): array
    {
        global $USER;
        $userId = (int)$USER->GetID();

        Loader::includeModule('app.orders');

        $record = ReportTaskTable::getList([
            'select' => ['STATUS', 'FILE_URL'],
            'filter' => [
                '=ID' => $taskId,
                '=USER_ID' => $userId
            ]
        ])->fetch();

        if (!$record) {
            return ['status' => 'error', 'message' => 'Task not found'];
        }

        // Приводим ключи к нижнему регистру для Vue фронтенда
        return [
            'status' => $record['STATUS'],
            'file_url' => $record['FILE_URL']
        ];
    }

    /**
     * AJAX Action: Скачать файл отчета.
     */
    public function downloadReportAction(int $taskId)
    {
        global $USER;
        $userId = (int)$USER->GetID();

        Loader::includeModule('app.orders');

        $record = ReportTaskTable::getList([
            'select' => ['FILE_URL'],
            'filter' => [
                '=ID' => $taskId,
                '=USER_ID' => $userId,
                '=STATUS' => 'done'
            ]
        ])->fetch();

        if (!$record || empty($record['FILE_URL'])) {
            header("HTTP/1.0 404 Not Found");
            echo "Файл не найден или доступ к нему запрещен.";
            die();
        }

        $filePath = $_SERVER['DOCUMENT_ROOT'] . $record['FILE_URL'];

        if (!file_exists($filePath)) {
            header("HTTP/1.0 404 Not Found");
            echo "Физический файл отчета отсутствует на сервере.";
            die();
        }

        // Очищаем буфер вывода (Output Buffer), чтобы случайные пробелы 
        // или варнинги из других файлов Битрикса не попали внутрь CSV и не повредили его.
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Отдаем файл
        header('Content-Description: File Transfer');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        die();
    }

    public function executeComponent()
    {
        \CJSCore::Init(['ajax']);
        $this->includeComponentTemplate();
    }
}