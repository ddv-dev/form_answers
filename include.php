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
    public static function OnFormResultListGetTabs($WEB_FORM_ID, &$arTabs, &$arAdditionalParams)
    {
        if (!Loader::includeModule("form.answers"))
            return;
            
        $options = Option::get("form.answers", "form_options", "");
        $options = unserialize($options);
        
        if (!is_array($options))
            $options = array();
            
        if (isset($options[$WEB_FORM_ID]) && $options[$WEB_FORM_ID] == "Y")
        {
            $arTabs[] = array(
                "DIV" => "answers_tab",
                "TAB" => "Ответы",
                "FILENAME" => "/bitrix/admin/form_answers_admin.php",
                "TITLE" => "Управление ответами на результаты",
                "ONSELECT" => ""
            );
        }
    }
}
?>