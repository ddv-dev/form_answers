<?
IncludeModuleLangFile(__FILE__);

if ($APPLICATION->GetGroupRight("form.answers") != "D")
{
    // Получаем список форм, для которых включены ответы
    $submenu = array();
    
    if (CModule::IncludeModule('form') && CModule::IncludeModule('form.answers'))
    {
        $rsForms = CForm::GetList('s_id', 'asc', array());
        while ($arForm = $rsForms->Fetch())
        {
            if (CFormAnswers::getFormOption($arForm['ID']) == 'Y')
            {
                $submenu[] = array(
                    'text' => htmlspecialcharsbx($arForm['NAME']),
                    'url' => 'form_answers_admin.php?WEB_FORM_ID='.$arForm['ID'].'&lang='.LANGUAGE_ID,
                    'title' => 'Ответы для формы: '.$arForm['NAME'],
                );
            }
        }
    }
    
    $aMenu = array(
        "parent_menu" => "global_menu_services",
        "section" => "form_answers",
        "sort" => 500,
        "text" => "Ответы на формы",
        "title" => "Управление ответами на результаты форм",
        "icon" => "form_menu_icon",
        "page_icon" => "form_page_icon",
        "items_id" => "menu_form_answers",
        "items" => array_merge(
            array(
                array(
                    "text" => "Настройки модуля",
                    "url" => "form_answers_settings.php?lang=".LANGUAGE_ID,
                    "title" => "Настройка ответов",
                ),
            ),
            !empty($submenu) ? array(
                array(
                    "text" => "─ Формы с ответами ─",
                    "url" => "",
                    "title" => "",
                    "items_id" => "menu_form_answers_forms",
                    "items" => $submenu
                )
            ) : array()
        )
    );
    
    return $aMenu;
}
return false;
?>