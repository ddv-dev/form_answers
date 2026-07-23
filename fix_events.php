<?php
/**
 * Разовый скрипт восстановления модуля form.answers через штатное API
 * Битрикса (а не прямые SQL-запросы):
 *   - регистрирует модуль в b_module, если он не зарегистрирован;
 *   - регистрирует корректный обработчик вкладки "Ответы"
 *     (main / OnAdminTabControlBegin), убирая дубли и старую подписку;
 *   - создаёт инфоблок "Ответы" с полями ID_RESULT/ID_FORM, если его нет.
 *
 * Можно запустить как через браузер (под учёткой администратора), так и
 * через php-cli - в CLI $_SERVER["DOCUMENT_ROOT"] не задан, поэтому корень
 * сайта вычисляется из собственного пути файла. Работает и из корня сайта,
 * и из каталога модуля (bitrix/modules/form.answers/fix_events.php).
 * После успешного запуска файл нужно удалить с сервера.
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
{
    die("Не удалось найти bitrix/modules/main/include/prolog_before.php относительно \"{$documentRoot}\".\n"
        ."Запустите скрипт из корня сайта или из bitrix/modules/form.answers/, либо задайте DOCUMENT_ROOT вручную.\n");
}

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($documentRoot."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;

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

// 4. Инфоблок "Ответы" - переиспользуем штатный CreateIBlock() из установщика,
// чтобы логика создания жила в одном месте (install/index.php).
$iblockId = intval(Option::get("form.answers", "answers_iblock_id", 0));
if ($iblockId > 0)
{
    echo "4. Инфоблок ответов уже существует (ID: {$iblockId})\n";
}
else
{
    $installFile = $documentRoot."/bitrix/modules/form.answers/install/index.php";
    if (is_file($installFile))
    {
        require_once($installFile);
        if (class_exists("form_answers"))
        {
            $installer = new form_answers();
            $installer->CreateIBlock();
            $iblockId = intval(Option::get("form.answers", "answers_iblock_id", 0));
            if ($iblockId > 0)
                echo "4. Инфоблок ответов создан (ID: {$iblockId}), поля ID_RESULT / ID_FORM добавлены\n";
            else
                echo "4. НЕ удалось создать инфоблок: "
                    .(!empty($installer->errors) ? $installer->errors : "проверьте, что модуль iblock установлен")."\n";
        }
        else
        {
            echo "4. Класс установщика form_answers не найден (install/index.php не подключился)\n";
        }
    }
    else
    {
        echo "4. Файл установщика не найден: {$installFile}\n";
    }
}

echo "\nГотово. Проверьте вкладку \"Ответы\" в результатах веб-формы,\n";
echo "предварительно включив её для нужной формы в настройках модуля.\n";
echo "\nПосле успешной проверки удалите этот файл с сервера.\n";
