<?php
/**
 * Разовый скрипт: регистрирует корректный обработчик вкладки "Ответы"
 * (main / OnAdminTabControlBegin) и, если модуль ещё не зарегистрирован
 * в b_module, регистрирует его - через штатное API Битрикса, а не через
 * прямые SQL-запросы.
 *
 * Положите файл в корень сайта (рядом с bitrix/) и откройте в браузере
 * ПОД учёткой администратора, либо запустите через php-cli. После
 * успешного запуска файл нужно удалить с сервера.
 */

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

if (php_sapi_type() !== "cli")
{
    global $USER;
    if (!$USER->IsAdmin())
    {
        die("Доступ только для администратора.\n");
    }
}

echo "=== Исправление обработчика вкладки \"Ответы\" ===\n\n";

if (!ModuleManager::isModuleInstalled("form.answers"))
{
    ModuleManager::registerModule("form.answers");
    echo "1. Модуль form.answers зарегистрирован в b_module\n";
}
else
{
    echo "1. Модуль form.answers уже зарегистрирован\n";
}

$em = EventManager::getInstance();

// Снимаем старый, нерабочий обработчик, если он был когда-то записан
$em->unRegisterEventHandler(
    "form",
    "OnFormResultListGetTabs",
    "form.answers",
    "CFormAnswersHandlers",
    "OnFormResultListGetTabs"
);
echo "2. Старый обработчик form/OnFormResultListGetTabs удалён (если был)\n";

// Убираем возможный дубль правильного обработчика перед повторной регистрацией
$em->unRegisterEventHandler(
    "main",
    "OnAdminTabControlBegin",
    "form.answers",
    "CFormAnswersHandlers",
    "OnAdminTabControlBegin"
);

$em->registerEventHandler(
    "main",
    "OnAdminTabControlBegin",
    "form.answers",
    "CFormAnswersHandlers",
    "OnAdminTabControlBegin"
);
echo "3. Обработчик main/OnAdminTabControlBegin зарегистрирован\n";

echo "\nГотово. Проверьте вкладку \"Ответы\" в результатах веб-формы,\n";
echo "предварительно включив её для нужной формы в настройках модуля.\n";
echo "\nПосле успешной проверки удалите этот файл с сервера.\n";
