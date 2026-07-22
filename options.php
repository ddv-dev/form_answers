<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/form/include.php");

if (!CModule::IncludeModule("form.answers"))
    return;

CModule::IncludeModule("form");

$APPLICATION->SetTitle("Настройки модуля ответов на формы");

// Сохранение
if ($_SERVER["REQUEST_METHOD"] == "POST" && check_bitrix_sessid())
{
    if (isset($_POST["forms"]) && is_array($_POST["forms"]))
    {
        foreach ($_POST["forms"] as $formId => $value)
            CFormAnswers::setFormOption($formId, $value);
    }
    LocalRedirect($APPLICATION->GetCurPage()."?lang=".LANGUAGE_ID."&mid=form.answers&mid_menu=1");
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

$tabControl = new CAdminTabControl("tabControl", array(
    array(
        "DIV" => "edit1",
        "TAB" => "Настройки",
        "TITLE" => "Настройки модуля"
    ),
));

$tabControl->Begin();
?>

<form method="POST" action="<?=$APPLICATION->GetCurPage()?>?lang=<?=LANGUAGE_ID?>&mid=form.answers&mid_menu=1">
    <?=bitrix_sessid_post()?>
    <?$tabControl->BeginNextTab();?>
    
    <tr>
        <td width="50%">
            <b>Выберите формы:</b>
        </td>
        <td width="50%">
            <?
            $rsForms = CForm::GetList("s_id", "asc", array());
            while ($arForm = $rsForms->Fetch())
            {
                $checked = CFormAnswers::getFormOption($arForm["ID"]) == "Y" ? "checked" : "";
                ?>
                <div style="margin:5px 0;">
                    <input type="checkbox" name="forms[<?=$arForm["ID"]?>]" value="Y" <?=$checked?>>
                    <?=htmlspecialcharsbx($arForm["NAME"])?> (ID: <?=$arForm["ID"]?>)
                </div>
                <?
            }
            ?>
        </td>
    </tr>
    
    <?$tabControl->Buttons();?>
    <input type="submit" value="Сохранить" class="adm-btn-save">
    <?$tabControl->End();?>
</form>

<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>