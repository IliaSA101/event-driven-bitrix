<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
    "NAME" => "История заказов (Vue + CQRS)",
    "DESCRIPTION" => "Выводит список заказов из микросервиса и позволяет асинхронно генерировать отчеты.",
    "PATH" => array(
        "ID" => "app",
        "NAME" => "Наши компоненты"
    ),
    "CACHE_PATH" => "Y",
    "COMPLEX" => "N"
);