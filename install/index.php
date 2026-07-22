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
        
        // Удаляем события
        $this->UnInstallEvents();
        
        // Удаляем файлы
        $this->UnInstallFiles();
        
        // Удаляем опции
        Option::delete($this->MODULE_ID);
        
        // Удаляем модуль из системы
        ModuleManager::unRegisterModule($this->MODULE_ID);
        
        // Показываем сообщение
        $APPLICATION->IncludeAdminFile(
            GetMessage("FORM_ANSWERS_UNINSTALL_TITLE"),
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/install/unstep1.php"
        );
        
        return true;
    }
    
    function InstallFiles($arParams = array())
    {
        // Копируем файлы в /bitrix/admin/
        CopyDirFiles(
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/admin",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin",
            true,
            true
        );
        return true;
    }
    
    function UnInstallFiles()
    {
        // Удаляем файлы из /bitrix/admin/
        DeleteDirFiles(
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/admin",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin"
        );
        return true;
    }
    
    function InstallEvents()
    {
        EventManager::getInstance()->registerEventHandler(
            "form",
            "OnFormResultListGetTabs",
            $this->MODULE_ID,
            "CFormAnswersHandlers",
            "OnFormResultListGetTabs"
        );
        return true;
    }
    
    function UnInstallEvents()
    {
        EventManager::getInstance()->unRegisterEventHandler(
            "form",
            "OnFormResultListGetTabs",
            $this->MODULE_ID,
            "CFormAnswersHandlers",
            "OnFormResultListGetTabs"
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
                return true;
            }
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
            // Создаем свойство "ID_RESULT"
            $ibp = new CIBlockProperty;
            $arPropFields = array(
                "NAME" => "ID результата",
                "ACTIVE" => "Y",
                "SORT" => 100,
                "CODE" => "ID_RESULT",
                "PROPERTY_TYPE" => "N",
                "IBLOCK_ID" => $newIblockId,
                "IS_REQUIRED" => "Y",
                "FILTRABLE" => "Y"
            );
            $ibp->Add($arPropFields);
            
            // Создаем свойство "ID_FORM"
            $arPropFields = array(
                "NAME" => "ID формы",
                "ACTIVE" => "Y",
                "SORT" => 200,
                "CODE" => "ID_FORM",
                "PROPERTY_TYPE" => "N",
                "IBLOCK_ID" => $newIblockId,
                "IS_REQUIRED" => "Y",
                "FILTRABLE" => "Y"
            );
            $ibp->Add($arPropFields);
            
            // Сохраняем ID инфоблока в настройках модуля
            Option::set($this->MODULE_ID, "answers_iblock_id", $newIblockId);
        }
        else
        {
            $this->errors = $ib->LAST_ERROR;
        }
        
        return $newIblockId;
    }
}
?>