<?php
/**
 * Диагностика вкладки "Ответы" (модуль form.answers).
 *
 * Проверяет по шагам всё, от чего зависит появление вкладки на
 * form_result_edit.php, и печатает, где именно обрыв. Ничего не ломает,
 * только читает (и по желанию чистит кэш событий).
 *
 * Запуск из каталога модуля:
 *   php /var/www/bitrix_site/bitrix/modules/form.answers/form_answers_diag.php
 * или через браузер под администратором.
 *
 * После проверки удалите файл с сервера.
 */

$documentRoot = isset($_SERVER["DOCUMENT_ROOT"]) ? $_SERVER["DOCUMENT_ROOT"] : "";
if ($documentRoot === "" || !is_file($documentRoot."/bitrix/modules/main/include/prolog_before.php"))
{
    $selfPath = str_replace("\\", "/", __FILE__);
    $marker = "/bitrix/modules/form.answers";
    $pos = strrpos($selfPath, $marker);
    $documentRoot = ($pos !== false)
        ? substr($selfPath, 0, $pos)
        : rtrim(str_replace("\\", "/", dirname(__FILE__)), "/");
}
if (!is_file($documentRoot."/bitrix/modules/main/include/prolog_before.php"))
    die("Не найден prolog_before.php относительно \"{$documentRoot}\".\n");

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($documentRoot."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

if (php_sapi_type() !== "cli")
{
    global $USER;
    if (!$USER->IsAdmin())
        die("Доступ только для администратора.\n");
    header("Content-Type: text/plain; charset=utf-8");
}

$ok = " [OK]  ";
$no = " [FAIL]";

echo "=== Диагностика вкладки \"Ответы\" ===\n\n";

// 1. Модуль зарегистрирован в системе
$installed = ModuleManager::isModuleInstalled("form.answers");
echo ($installed ? $ok : $no)." 1. Модуль form.answers "
    .($installed ? "зарегистрирован в b_module\n" : "НЕ зарегистрирован в b_module\n");

// 2. Модуль подключается (include.php исполняется без ошибок)
$loaded = Loader::includeModule("form.answers");
echo ($loaded ? $ok : $no)." 2. Loader::includeModule('form.answers') = "
    .($loaded ? "true\n" : "false — include.php не подключился\n");

// 3. Класс-обработчик и НУЖНЫЙ метод существуют (ловит старый include.php на сервере)
$hasClass = class_exists("CFormAnswersHandlers");
$hasMethod = $hasClass && method_exists("CFormAnswersHandlers", "OnAdminTabControlBegin");
echo ($hasClass ? $ok : $no)." 3a. Класс CFormAnswersHandlers "
    .($hasClass ? "определён\n" : "НЕ определён (include.php не загрузился?)\n");
echo ($hasMethod ? $ok : $no)." 3b. Метод OnAdminTabControlBegin "
    .($hasMethod ? "существует\n" : "ОТСУТСТВУЕТ — на сервере СТАРЫЙ include.php, обновите файл\n");

// Заодно предупредим, если на сервере остался старый метод
$hasOldMethod = $hasClass && method_exists("CFormAnswersHandlers", "OnFormResultListGetTabs");
if ($hasOldMethod)
    echo "        (!) Найден старый метод OnFormResultListGetTabs — это тоже признак старого include.php\n";

// 4. Обработчик события зарегистрирован в main/OnAdminTabControlBegin
$handlers = EventManager::getInstance()->findEventHandlers("main", "OnAdminTabControlBegin");
$found = false;
foreach ($handlers as $h)
{
    $cls = isset($h["TO_CLASS"]) ? $h["TO_CLASS"] : "";
    $mth = isset($h["TO_METHOD"]) ? $h["TO_METHOD"] : "";
    if ($cls === "CFormAnswersHandlers" && $mth === "OnAdminTabControlBegin")
        $found = true;
}
echo ($found ? $ok : $no)." 4. Обработчик main/OnAdminTabControlBegin -> "
    ."CFormAnswersHandlers::OnAdminTabControlBegin "
    .($found ? "зарегистрирован\n" : "НЕ найден среди обработчиков события\n");
echo "        Всего обработчиков OnAdminTabControlBegin: ".count($handlers)."\n";

// 5. Старый (нерабочий) обработчик не должен висеть
$oldHandlers = EventManager::getInstance()->findEventHandlers("form", "OnFormResultListGetTabs");
if (!empty($oldHandlers))
    echo "        (!) В form/OnFormResultListGetTabs всё ещё есть ".count($oldHandlers)
        ." обработчик(ов) — событие несуществующее, можно снять.\n";

// 6. ID инфоблока
$iblockId = intval(Option::get("form.answers", "answers_iblock_id", 0));
echo (($iblockId > 0) ? $ok : $no)." 5. answers_iblock_id = ".$iblockId
    .(($iblockId > 0) ? "\n" : " — инфоблок ответов не создан\n");

// 7. Для каких форм включена галка ответов
echo "\n--- Формы и состояние галки \"ответы\" ---\n";
if (Loader::includeModule("form"))
{
    $rs = CForm::GetList($by = "s_id", $order = "asc", array());
    $any = false;
    while ($f = $rs->Fetch())
    {
        $any = true;
        $val = CFormAnswers::getFormOption($f["ID"]);
        echo "   Форма #".$f["ID"]." «".$f["NAME"]."»: ответы = ".$val
            .($val === "Y" ? "  <-- вкладка должна показываться" : "")."\n";
    }
    if (!$any)
        echo "   (веб-форм в системе не найдено)\n";
}
else
{
    echo "   (модуль form не подключился)\n";
}

// 8. Чистим кэш событий/меню — на случай, если регистрация не подхватилась
try {
    $managedCache = \Bitrix\Main\Application::getInstance()->getManagedCache();
    $managedCache->clean("b_module_to_module");
    echo "\n[i] Кэш обработчиков событий (b_module_to_module) очищен.\n";
} catch (\Throwable $e) {
    echo "\n[i] Не удалось очистить кэш событий: ".$e->getMessage()."\n";
}

echo "\n=== Итог ===\n";
if ($installed && $loaded && $hasMethod && $found && $iblockId > 0)
{
    echo "Все технические условия выполнены. Если вкладки всё ещё нет:\n";
    echo " - убедитесь, что для нужной формы выше стоит «ответы = Y»;\n";
    echo " - откройте именно РЕДАКТИРОВАНИЕ результата этой формы\n";
    echo "   (form_result_edit.php?WEB_FORM_ID=<id>&RESULT_ID=<id>);\n";
    echo " - сбросьте кэш сайта целиком (Настройки → Кэш → Очистить весь кэш)\n";
    echo "   и обновите страницу.\n";
}
else
{
    echo "Есть проваленные пункты выше — начните с первого [FAIL].\n";
    if (!$hasMethod)
        echo " Скорее всего: на сервере лежит СТАРЫЙ include.php. Обновите\n"
            ." /bitrix/modules/form.answers/include.php из репозитория.\n";
    elseif (!$found)
        echo " Скорее всего: обработчик не зарегистрирован. Запустите fix_events.php.\n";
}

echo "\nПосле проверки удалите этот файл с сервера.\n";
