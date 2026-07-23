<?
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

class CFormAnswers
{
    public static function getAnswersIBlockId()
    {
        return Option::get("form.answers", "answers_iblock_id", 0);
    }
    
    public static function getFormOption($formId)
    {
        $options = Option::get("form.answers", "form_options", "");
        if (!empty($options))
            $options = unserialize($options);
        else
            $options = array();
            
        return isset($options[$formId]) ? $options[$formId] : "N";
    }
    
    public static function setFormOption($formId, $value)
    {
        $options = Option::get("form.answers", "form_options", "");
        if (!empty($options))
            $options = unserialize($options);
        else
            $options = array();
            
        $options[$formId] = ($value == "Y" ? "Y" : "N");
        Option::set("form.answers", "form_options", serialize($options));
    }
    
    public static function getAnswers($resultId, $formId)
    {
        if (!Loader::includeModule("iblock"))
            return array();
            
        $iblockId = self::getAnswersIBlockId();
        if ($iblockId <= 0)
            return array();
        
        $arFilter = array(
            "IBLOCK_ID" => $iblockId,
            "ACTIVE" => "Y",
            "PROPERTY_ID_RESULT" => $resultId,
            "PROPERTY_ID_FORM" => $formId,
        );
        
        $arSelect = array("ID", "NAME", "DETAIL_TEXT", "DETAIL_TEXT_TYPE", "DATE_CREATE");
        
        $res = CIBlockElement::GetList(
            array("ID" => "ASC"),
            $arFilter,
            false,
            false,
            $arSelect
        );
        
        $arAnswers = array();
        while ($ob = $res->GetNextElement())
        {
            $arFields = $ob->GetFields();
            $arAnswers[] = $arFields;
        }
        
        return $arAnswers;
    }
}

// Обработчик событий
class CFormAnswersHandlers
{
    /**
     * В модуле "form" (веб-формы) нет события "OnFormResultListGetTabs" - его не существует
     * в ядре Битрикс, поэтому оно никогда не вызывается, и вкладка не появлялась.
     * Добавлять свою вкладку в CAdminTabControl нужно через общее событие главного
     * модуля "main" - "OnAdminTabControlBegin", которое стреляет из CAdminTabControl::Begin()
     * для КАЖДОГО экрана редактирования в админке, поэтому здесь обязательно фильтруем
     * по имени скрипта (form_result_edit.php).
     */
    public static function OnAdminTabControlBegin(&$tabControl)
    {
        if (!Loader::includeModule("form.answers"))
            return;

        if (!isset($tabControl->tabs) || !is_array($tabControl->tabs))
            return;

        if (basename($_SERVER["SCRIPT_NAME"]) !== "form_result_edit.php")
            return;

        $formId = intval($_REQUEST["WEB_FORM_ID"]);
        if ($formId <= 0 || CFormAnswers::getFormOption($formId) !== "Y")
            return;

        $resultId = intval($_REQUEST["RESULT_ID"]);

        // Своя форма (выбор результата + текст/HTML/визуальный редактор) не может быть
        // вложена как <form> внутрь CONTENT-вкладки - вся страница form_result_edit.php
        // уже обёрнута в один общий <form>, а вложенные <form> в HTML не поддерживаются.
        // Поэтому подключаем существующую рабочую страницу через iframe.
        $src = "/bitrix/admin/form_answers_admin.php?IFRAME=Y&WEB_FORM_ID=".$formId
            .($resultId > 0 ? "&RESULT_ID=".$resultId : "")
            ."&lang=".LANGUAGE_ID;

        // iframe без внутреннего скролла: JS подгоняет его высоту под реальную
        // высоту содержимого (страница того же домена, размеры доступны).
        // Ресайз выполняется только когда вкладка видима (offsetWidth > 0), и
        // периодически - чтобы учесть позднюю инициализацию визуального редактора.
        $iframeId = "form_answers_iframe";
        $content =
            '<iframe id="'.$iframeId.'" src="'.htmlspecialcharsbx($src).'" scrolling="no"'
            .' style="width:100%;height:400px;border:0;display:block;overflow:hidden;"></iframe>'
            .'<script>(function(){'
            .'var id='.json_encode($iframeId).';'
            .'function rz(){var f=document.getElementById(id);if(!f||!f.offsetWidth)return;'
            .'try{var d=f.contentWindow.document;'
            .'var h=Math.max(d.body.scrollHeight,d.body.offsetHeight,d.documentElement.scrollHeight);'
            .'if(h>0&&Math.abs(parseInt(f.style.height)-h)>2)f.style.height=(h+24)+"px";}catch(e){}}'
            .'var f=document.getElementById(id);if(f){f.addEventListener("load",function(){setTimeout(rz,200);});}'
            .'setInterval(rz,500);'
            .'})();</script>';

        $tabControl->tabs[] = array(
            "DIV" => "answers_tab",
            "TAB" => "Ответы",
            "TITLE" => "Ответы на результат формы",
            "CONTENT" => $content,
        );
    }
}
?>