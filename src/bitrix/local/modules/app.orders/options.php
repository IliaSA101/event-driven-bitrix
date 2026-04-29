<?php

use Bitrix\Main\HttpApplication;
use Bitrix\Main\Config\Option;

// Защита от прямого подключения
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$moduleId = 'app.orders';
$request = HttpApplication::getInstance()->getContext()->getRequest();

// Проверка прав доступа к модулю
if ($APPLICATION->GetGroupRight($moduleId) < "S") {
    $APPLICATION->AuthForm("Доступ запрещен");
}

// Сохранение настроек
if ($request->isPost() && $request->getPost('Update') && check_bitrix_sessid()) {
    Option::set($moduleId, 'rabbitmq_host', $request->getPost('rabbitmq_host'));
    Option::set($moduleId, 'rabbitmq_port', $request->getPost('rabbitmq_port'));
    Option::set($moduleId, 'rabbitmq_user', $request->getPost('rabbitmq_user'));
    Option::set($moduleId, 'rabbitmq_password', $request->getPost('rabbitmq_password'));
    Option::set($moduleId, 'api_key', $request->getPost('api_key'));
}

// Получение текущих значений (или дефолтных)
$host = Option::get($moduleId, 'rabbitmq_host', 'rabbitmq');
$port = Option::get($moduleId, 'rabbitmq_port', '15672');
$user = Option::get($moduleId, 'rabbitmq_user', 'guest');
$pass = Option::get($moduleId, 'rabbitmq_password', 'guest');
$apiKey = Option::get($moduleId, 'api_key', 'SecretToken123');

// Описание вкладок
$aTabs = [
    [
        "DIV" => "edit1",
        "TAB" => "Настройки интеграции",
        "ICON" => "main_settings",
        "TITLE" => "Параметры подключения к микросервису и RabbitMQ"
    ]
];

$tabControl = new CAdminTabControl("tabControl", $aTabs);
?>

<form method="post" action="<?php echo $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($moduleId) ?>&amp;lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    
    <?php $tabControl->Begin(); ?>
    <?php $tabControl->BeginNextTab(); ?>

    <tr>
        <td width="40%">
            <label for="rabbitmq_password">Пароль RabbitMQ:</label>
        </td>
        <td width="60%">
            <input type="password" size="50" maxlength="255" id="rabbitmq_password" name="rabbitmq_password" value="<?= htmlspecialcharsbx($pass) ?>" />
        </td>
    </tr>

    <tr>
        <td width="40%">
            <label for="api_key">API Ключ (Microservice):</label>
        </td>
        <td width="60%">
            <input type="text" size="50" maxlength="255" id="api_key" name="api_key" value="<?= htmlspecialcharsbx($apiKey) ?>" />
            <br><small>Должен совпадать с MICROSERVICE_API_KEY в .env файле</small>
        </td>
    </tr>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="Сохранить настройки" title="Сохранить и применить" class="adm-btn-save">
    
    <?php $tabControl->End(); ?>
</form>