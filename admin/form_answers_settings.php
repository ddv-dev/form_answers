<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/form/include.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/form.answers/include.php");

if ($APPLICATION->GetGroupRight("form.answers") == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

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
    CAdminMessage::ShowNote("Настройки сохранены");
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
?>

<form method="POST">
    <?=bitrix_sessid_post()?>
    
    <div class="adm-detail-content-wrap">
        <div class="adm-detail-content">
            <div class="adm-detail-title">Включить ответы для форм</div>
            <div class="adm-detail-content-item-block">
                <table class="adm-list-table">
                    <tr class="adm-list-table-header">
                        <td class="adm-list-table-cell">ID</td>
                        <td class="adm-list-table-cell">Название формы</td>
                        <td class="adm-list-table-cell">Ответы</td>
                    </tr>
                    <?
                    $rsForms = CForm::GetList("s_id", "asc", array());
                    while ($arForm = $rsForms->Fetch())
                    {
                        $checked = CFormAnswers::getFormOption($arForm["ID"]) == "Y" ? "checked" : "";
                        ?>
                        <tr class="adm-list-table-row">
                            <td class="adm-list-table-cell"><?=$arForm["ID"]?></td>
                            <td class="adm-list-table-cell"><?=htmlspecialcharsbx($arForm["NAME"])?></td>
                            <td class="adm-list-table-cell">
                                <input type="checkbox" name="forms[<?=$arForm["ID"]?>]" value="Y" <?=$checked?>>
                            </td>
                        </tr>
                        <?
                    }
                    ?>
                </table>
            </div>
        </div>
        <div class="adm-detail-content-btns-wrap">
            <div class="adm-detail-content-btns">
                <input type="submit" value="Сохранить" class="adm-btn-save">
            </div>
        </div>
    </div>
</form>

<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>