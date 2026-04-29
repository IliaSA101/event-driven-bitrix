<?php

namespace App\Orders\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\Type\DateTime;

/**
 * ORM сущность для таблицы report_tasks.
 * Обеспечивает безопасную работу с БД через абстракцию DataManager.
 */
class ReportTaskTable extends DataManager
{
    /**
     * Возвращает имя физической таблицы в БД.
     */
    public static function getTableName(): string
    {
        return 'report_tasks';
    }

    /**
     * Возвращает карту полей таблицы.
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
                'title' => 'ID Задачи'
            ]),
            
            new IntegerField('USER_ID', [
                'required' => true,
                'title' => 'ID Пользователя'
            ]),
            
            new EnumField('STATUS', [
                'values' => ['pending', 'processing', 'done', 'error'],
                'default_value' => 'pending',
                'title' => 'Статус задачи'
            ]),
            
            new StringField('FILE_URL', [
                'title' => 'Путь к файлу отчета'
            ]),
            
            new DatetimeField('CREATED_AT', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата создания'
            ]),
            
            new DatetimeField('UPDATED_AT', [
                'default_value' => function() {
                    return new DateTime();
                },
                'title' => 'Дата обновления'
            ]),
        ];
    }
}