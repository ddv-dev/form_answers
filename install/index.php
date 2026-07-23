<?
/**
 * Модуль "Ответы на результаты веб-форм"
 * Класс модуля: form_answers
 */
use Bitrix\Main\ModuleManager;
use Bitrix\Main\EventManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

// Подключаем языковой файл
IncludeModuleLangFile(__FILE__);

class form_answers extends CModule
{
    public $MODULE_ID = "form.answers";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_CSS;
    public $errors;

    function __construct()
    {
        $arModuleVersion = array();
        
        // Получаем версию модуля
        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path."/version.php");
        
        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }
        else
        {
            $this->MODULE_VERSION = "1.0.0";
            $this->MODULE_VERSION_DATE = "2024-01-01 00:00:00";
        }
        
        $this->MODULE_NAME = GetMessage("FORM_ANSWERS_MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("FORM_ANSWERS_MODULE_DESCRIPTION");
        $this->PARTNER_NAME = "";
        $this->PARTNER_URI = "";
    }

    function DoInstall()
    {
        global $APPLICATION, $step;
        
        $this->errors = false;
        
        // Установка файлов
        $this->InstallFiles();
        
        // Установка событий
        $this->InstallEvents();
        
        // Создание инфоблока для ответов
        $this->CreateIBlock();
        
        // Регистрация модуля в системе
        ModuleManager::registerModule($this->MODULE_ID);
        
        // Показываем сообщение об успешной установке
        $APPLICATION->IncludeAdminFile(
            GetMessage("FORM_ANSWERS_INSTALL_TITLE"),
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/step1.php"
        );
        
        return true;
    }

    function DoUninstall()
    {
        global $APPLICATION, $step;

        // Одношаговое удаление: снимаем событие, страницы, опции и регистрацию.
        $this->UnInstallEvents();
        $this->UnInstallFiles();

        // Инфоблок с ответами удаляем по умолчанию. Чтобы сохранить данные
        // (например, перед переустановкой), добавьте в URL удаления save_data=Y.
        if ($_REQUEST["save_data"] != "Y")
            $this->DeleteIBlock();

        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            GetMessage("FORM_ANSWERS_UNINSTALL_TITLE"),
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/unstep1.php"
        );

        return true;
    }

    // Удаляет инфоблок ответов вместе со всеми ответами (и тип инфоблока,
    // если в нём не осталось других инфоблоков).
    function DeleteIBlock()
    {
        if (!Loader::includeModule("iblock"))
            return;

        $iblockId = intval(Option::get($this->MODULE_ID, "answers_iblock_id", 0));

        if ($iblockId <= 0)
        {
            // Опция могла потеряться - ищем инфоблок по коду.
            $rs = CIBlock::GetList(array(), array(
                "TYPE" => "form_answers",
                "CODE" => "form_answers",
                "CHECK_PERMISSIONS" => "N",
            ));
            if ($ib = $rs->Fetch())
                $iblockId = intval($ib["ID"]);
        }

        if ($iblockId > 0)
            CIBlock::Delete($iblockId); // удаляет инфоблок вместе с элементами и свойствами

        // Удаляем тип инфоблока, если в нём больше нет инфоблоков.
        $rsRest = CIBlock::GetList(array(), array(
            "TYPE" => "form_answers",
            "CHECK_PERMISSIONS" => "N",
        ));
        if (!$rsRest->Fetch())
            CIBlockType::Delete("form_answers");
    }
    
    // Страницы модуля, которые должны быть доступны по URL из /bitrix/admin/.
    // menu.php сюда НЕ входит: он подхватывается ядром прямо из каталога модуля
    // (bitrix/modules/form.answers/admin/menu.php), а копирование его в
    // /bitrix/admin/ перезаписало бы штатный файл меню ядра.
    protected $adminPages = array(
        "form_answers_admin.php",
        "form_answers_settings.php",
    );

    function InstallFiles($arParams = array())
    {
        $from = $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/admin/";
        $to   = $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin/";

        foreach ($this->adminPages as $file)
            CopyDirFiles($from.$file, $to.$file, true, false);

        return true;
    }

    function UnInstallFiles()
    {
        $to = $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin/";

        foreach ($this->adminPages as $file)
        {
            if (file_exists($to.$file))
                @unlink($to.$file);
        }

        return true;
    }
    
    function InstallEvents()
    {
        // "OnFormResultListGetTabs" в модуле "form" не существует - такого события
        // ядро Битрикс никогда не генерирует. Добавлять вкладку в CAdminTabControl
        // нужно через общее событие главного модуля "main" - "OnAdminTabControlBegin".
        EventManager::getInstance()->registerEventHandler(
            "main",
            "OnAdminTabControlBegin",
            $this->MODULE_ID,
            "CFormAnswersHandlers",
            "OnAdminTabControlBegin"
        );
        return true;
    }

    function UnInstallEvents()
    {
        EventManager::getInstance()->unRegisterEventHandler(
            "main",
            "OnAdminTabControlBegin",
            $this->MODULE_ID,
            "CFormAnswersHandlers",
            "OnAdminTabControlBegin"
        );
        return true;
    }
    
    function CreateIBlock()
    {
        if (!Loader::includeModule("iblock"))
            return false;
            
        // Проверяем, существует ли уже инфоблок
        $iblockId = Option::get($this->MODULE_ID, "answers_iblock_id", 0);
        
        if ($iblockId > 0)
        {
            $res = CIBlock::GetList(
                array(), 
                array(
                    "ID" => $iblockId, 
                    "CHECK_PERMISSIONS" => "N"
                )
            );
            
            if ($res->Fetch())
            {
                // Инфоблок уже существует
                $this->ensureIBlockProperties($iblockId);
                return true;
            }
        }

        // Инфоблок мог остаться от прошлой установки (удаление модуля стирает
        // только опцию, но не сам инфоблок). Переиспользуем его, чтобы не
        // плодить дубли и сохранить уже оставленные ответы.
        $res = CIBlock::GetList(array(), array(
            "TYPE" => "form_answers",
            "CODE" => "form_answers",
            "CHECK_PERMISSIONS" => "N",
        ));
        if ($existing = $res->Fetch())
        {
            Option::set($this->MODULE_ID, "answers_iblock_id", $existing["ID"]);
            $this->ensureIBlockProperties($existing["ID"]);
            return $existing["ID"];
        }

        // Создаем тип инфоблока если его нет
        $obType = new CIBlockType;
        $dbType = CIBlockType::GetByID("form_answers");
        
        if (!$dbType->Fetch())
        {
            $arFields = array(
                "ID" => "form_answers",
                "SECTIONS" => "N",
                "IN_RSS" => "N",
                "SORT" => 500,
                "LANG" => array(
                    "ru" => array(
                        "NAME" => "Ответы на формы",
                        "SECTION_NAME" => "",
                        "ELEMENT_NAME" => "Ответ"
                    ),
                    "en" => array(
                        "NAME" => "Form Answers",
                        "SECTION_NAME" => "",
                        "ELEMENT_NAME" => "Answer"
                    )
                )
            );
            $obType->Add($arFields);
        }
        
        // Создаем инфоблок
        $ib = new CIBlock;
        $arFields = array(
            "ACTIVE" => "Y",
            "NAME" => "Ответы на результаты форм",
            "CODE" => "form_answers",
            "IBLOCK_TYPE_ID" => "form_answers",
            "SITE_ID" => array("s1"),
            "SORT" => 500,
            "GROUP_ID" => array("1" => "X", "2" => "W"),
            "WORKFLOW" => "N",
            "INDEX_ELEMENT" => "Y",
            "DESCRIPTION_TYPE" => "text"
        );
        
        $newIblockId = $ib->Add($arFields);
        
        if ($newIblockId)
        {
            // Свойства "ID_RESULT" и "ID_FORM"
            $this->ensureIBlockProperties($newIblockId);

            // Сохраняем ID инфоблока в настройках модуля
            Option::set($this->MODULE_ID, "answers_iblock_id", $newIblockId);
        }
        else
        {
            $this->errors = $ib->LAST_ERROR;
        }

        return $newIblockId;
    }

    // Создаёт недостающие свойства ID_RESULT / ID_FORM в инфоблоке ответов.
    // Идемпотентно: существующие свойства не трогает (нужно при переустановке).
    protected function ensureIBlockProperties($iblockId)
    {
        if (intval($iblockId) <= 0 || !Loader::includeModule("iblock"))
            return;

        $props = array(
            array("CODE" => "ID_RESULT", "NAME" => "ID результата", "SORT" => 100),
            array("CODE" => "ID_FORM",   "NAME" => "ID формы",       "SORT" => 200),
        );

        foreach ($props as $p)
        {
            $rs = CIBlockProperty::GetList(array(), array(
                "IBLOCK_ID" => $iblockId,
                "CODE" => $p["CODE"],
            ));
            if ($rs->Fetch())
                continue;

            $ibp = new CIBlockProperty;
            $ibp->Add(array(
                "NAME" => $p["NAME"],
                "ACTIVE" => "Y",
                "SORT" => $p["SORT"],
                "CODE" => $p["CODE"],
                "PROPERTY_TYPE" => "N",
                "IBLOCK_ID" => $iblockId,
                "IS_REQUIRED" => "Y",
                "FILTRABLE" => "Y",
            ));
        }
    }
}
?>