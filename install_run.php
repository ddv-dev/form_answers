<?php
/**
 * Разовый скрипт: выполняет установку модуля form.answers напрямую, минуя
 * module_admin.php (кнопки/слайдер/sessid), и печатает подробный отчёт -
 * чтобы увидеть, если какой-то шаг установки падает.
 *
 * Запуск из каталога модуля:
 *   php /var/www/bitrix_site/bitrix/modules/form.answers/install_run.php
 * или через браузер под администратором.
 *
 * После успешной установки удалите файл с сервера.
 */

$documentRoot = isset($_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : "";
if ($documentRoot === "" || !is_file($documentRoot."/bitrix/modules/main/include/prolog_before.php")) {
    $self = str_replace("\\", "/", __FILE__);
    $pos = strrpos($self, "/bitrix/modules/form.answers");
    $documentRoot = ($pos !== false) ? substr($self, 0, $pos) : rtrim(str_replace("\\", "/", dirname(__FILE__)), "/");
}
if (!is_file($documentRoot."/bitrix/modules/main/include/prolog_before.php"))
    die("Не найден prolog_before.php относительно \"{$documentRoot}\".\n");

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($documentRoot."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

if (php_sapi_type() !== "cli") {
    global $USER;
    if (!$USER->IsAdmin()) die("Доступ только для администратора.\n");
    header("Content-Type: text/plain; charset=utf-8");
}

$MID = "form.answers";
echo "=== Установка модуля {$MID} ===\n\n";

echo "Статус до установки: ".(ModuleManager::isModuleInstalled($MID) ? "УСТАНОВЛЕН" : "не установлен")."\n\n";

// Подключаем класс установщика
$installFile = $documentRoot."/bitrix/modules/".$MID."/install/index.php";
if (!is_file($installFile)) die("Не найден {$installFile}\n");
require_once($installFile);

if (!class_exists("form_answers")) die("Класс form_answers не определён в install/index.php\n");

$m = new form_answers();

// Выполняем шаги установки по отдельности (как DoInstall, но без IncludeAdminFile,
// который завершил бы скрипт), и ловим ошибки.
try {
    echo "1. InstallFiles()... ";
    $m->InstallFiles();
    $to = $documentRoot."/bitrix/admin/";
    $f1 = is_file($to."form_answers_admin.php");
    $f2 = is_file($to."form_answers_settings.php");
    echo "OK (admin.php: ".($f1?"есть":"НЕТ").", settings.php: ".($f2?"есть":"НЕТ").")\n";

    echo "2. InstallEvents()... ";
    $m->InstallEvents();
    $handlers = EventManager::getInstance()->findEventHandlers("main", "OnAdminTabControlBegin");
    $found = false;
    foreach ($handlers as $h) {
        if (($h["TO_CLASS"] ?? "") === "CFormAnswersHandlers") $found = true;
    }
    echo "OK (обработчик OnAdminTabControlBegin: ".($found?"зарегистрирован":"НЕ найден").")\n";

    echo "3. CreateIBlock()... ";
    $res = $m->CreateIBlock();
    $iblockId = intval(Option::get($MID, "answers_iblock_id", 0));
    if (!empty($m->errors))
        echo "ОШИБКА: ".(is_array($m->errors) ? implode("; ", $m->errors) : $m->errors)."\n";
    else
        echo "OK (iblock id: {$iblockId})\n";

    echo "4. registerModule()... ";
    ModuleManager::registerModule($MID);
    echo "OK\n";

    echo "\nСтатус после установки: ".(ModuleManager::isModuleInstalled($MID) ? "УСТАНОВЛЕН" : "НЕ УСТАНОВЛЕН")."\n";
    echo "Loader::includeModule: ".(Loader::includeModule($MID) ? "true" : "false")."\n";

    echo "\n✅ Готово. Проверьте список модулей (обновите страницу) и вкладку «Ответы».\n";
    echo "После проверки удалите этот файл с сервера.\n";
} catch (\Throwable $e) {
    echo "\n❌ Исключение на установке: ".$e->getMessage()."\n";
    echo "Файл: ".$e->getFile().":".$e->getLine()."\n";
}
