<?php

use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;

class app_orders extends CModule
{
    public $MODULE_ID = "app.orders";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");

        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = "Интеграция с OrderService (CQRS)";
        $this->MODULE_DESCRIPTION = "Перехватывает события заказов и управляет отчетами через RabbitMQ.";
        $this->PARTNER_NAME = "App";
        $this->PARTNER_URI = "https://example.com";
    }

    public function DoInstall()
    {
        global $APPLICATION;
        
        if ($this->InstallDB()) {
            $this->InstallEvents();
            ModuleManager::registerModule($this->MODULE_ID);
        } else {
            $APPLICATION->ThrowException("Ошибка при установке базы данных модуля.");
        }
    }

    public function DoUninstall()
    {
        $this->UnInstallEvents();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    /**
     * Эталонный способ установки БД в Битрикс.
     * Читает .sql файлы и выполняет батч. Если есть ошибка синтаксиса — 
     * она корректно отловится и выведется в админке Битрикса.
     */
    public function InstallDB()
    {
        global $DB, $APPLICATION;
        
        $sqlFilePath = __DIR__ . "/db/mysql/install.sql";
        
        if (file_exists($sqlFilePath)) {
            $errors = $DB->RunSQLBatch($sqlFilePath);
            
            if ($errors !== false) {
                $APPLICATION->ThrowException(implode("<br>", $errors));
                return false;
            }
        }
        
        return true;
    }

    public function UnInstallDB()
    {
        global $DB, $APPLICATION;
        
        $sqlFilePath = __DIR__ . "/db/mysql/uninstall.sql";
        
        if (file_exists($sqlFilePath)) {
            $errors = $DB->RunSQLBatch($sqlFilePath);
            
            if ($errors !== false) {
                $APPLICATION->ThrowException(implode("<br>", $errors));
                return false;
            }
        }
        
        return true;
    }

    public function InstallEvents()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->registerEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            '\App\Orders\EventHandlers\OrderHandler',
            'onOrderSaved'
        );
    }

    public function UnInstallEvents()
    {
        $eventManager = EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            '\App\Orders\EventHandlers\OrderHandler',
            'onOrderSaved'
        );
    }
}