<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/form/include.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/form.answers/include.php");

if ($APPLICATION->GetGroupRight("form.answers") == "D")
    $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

CModule::IncludeModule("form");
CModule::IncludeModule("iblock");

$WEB_FORM_ID = intval($_REQUEST["WEB_FORM_ID"]);
$RESULT_ID = intval($_REQUEST["RESULT_ID"]);
$action = $_REQUEST["action"];

$z = CForm::GetByID($WEB_FORM_ID);
if (!$form = $z->Fetch())
{
    require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
    CAdminMessage::ShowMessage("Форма не найдена");
    require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

$APPLICATION->SetTitle("Ответы: ".$form["NAME"]);

// Удаление
if ($action == "delete" && ($delete_id = intval($_REQUEST["delete_id"])) > 0)
{
    if (check_bitrix_sessid())
    {
        CIBlockElement::Delete($delete_id);
        LocalRedirect($APPLICATION->GetCurPageParam("", array("delete_id", "action", "sessid")));
    }
}

// Сохранение
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_REQUEST["save"] == "Y")
{
    if (check_bitrix_sessid())
    {
        $iblockId = CFormAnswers::getAnswersIBlockId();
        
        if ($iblockId > 0 && $RESULT_ID > 0)
        {
            $el = new CIBlockElement;
            
            $arFields = array(
                "IBLOCK_ID" => $iblockId,
                "NAME" => "Ответ на результат #".$RESULT_ID." (форма #".$WEB_FORM_ID.")",
                "ACTIVE" => "Y",
                "DETAIL_TEXT" => $_REQUEST["ANSWER"],
                "DETAIL_TEXT_TYPE" => $_REQUEST["ANSWER_TYPE"] ?: "html",
                "PROPERTY_VALUES" => array(
                    "ID_RESULT" => $RESULT_ID,
                    "ID_FORM" => $WEB_FORM_ID,
                ),
            );
            
            $edit_id = intval($_REQUEST["edit_id"]);
            
            if ($edit_id > 0)
            {
                if ($el->Update($edit_id, $arFields))
                    CAdminMessage::ShowNote("Ответ обновлен");
                else
                    CAdminMessage::ShowMessage("Ошибка: ".$el->LAST_ERROR);
            }
            else
            {
                if ($el->Add($arFields))
                    CAdminMessage::ShowNote("Ответ добавлен");
                else
                    CAdminMessage::ShowMessage("Ошибка: ".$el->LAST_ERROR);
            }
            
            LocalRedirect($APPLICATION->GetCurPageParam("", array("edit_id", "save", "sessid")));
        }
    }
}

// Список результатов
$rsResults = CFormResult::GetList($WEB_FORM_ID, "s_id", "desc", array());
$arResults = array();
while ($arResult = $rsResults->Fetch())
    $arResults[] = $arResult;

// Редактирование
$editAnswer = null;
if (isset($_REQUEST["edit_id"]) && intval($_REQUEST["edit_id"]) > 0)
{
    $res = CIBlockElement::GetByID(intval($_REQUEST["edit_id"]));
    if ($arEdit = $res->GetNext())
    {
        $editAnswer = $arEdit;
        if (!$RESULT_ID)
        {
            $db_props = CIBlockElement::GetProperty($editAnswer["IBLOCK_ID"], $editAnswer["ID"], array(), array("CODE" => "ID_RESULT"));
            if ($ar_props = $db_props->Fetch())
                $RESULT_ID = $ar_props["VALUE"];
        }
    }
}

require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

// Визуальный редактор
if (CModule::IncludeModule("fileman"))
{
    CFileMan::AddHTMLEditorFrame(
        "ANSWER",
        $editAnswer ? $editAnswer["DETAIL_TEXT"] : "",
        "ANSWER_TYPE",
        $editAnswer ? $editAnswer["DETAIL_TEXT_TYPE"] : "html",
        array("height" => 400, "width" => "100%"),
        "N",
        0,
        "",
        ""
    );
}
?>

<div class="adm-detail-content-wrap">
    <div class="adm-detail-content">
        <div class="adm-detail-title">Выберите результат</div>
        <div class="adm-detail-content-item-block">
            <select onchange="if(this.value) window.location='?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID='+this.value">
                <option value="">-- выберите --</option>
                <?foreach($arResults as $res):?>
                    <option value="<?=$res["ID"]?>" <?=$res["ID"]==$RESULT_ID?"selected":""?>>
                        Результат #<?=$res["ID"]?> от <?=$res["DATE_CREATE"]?>
                    </option>
                <?endforeach;?>
            </select>
        </div>
    </div>
</div>

<?if($RESULT_ID > 0):?>
    <div class="adm-detail-content-wrap" style="margin-top:20px;">
        <div class="adm-detail-content">
            <div class="adm-detail-title"><?=$editAnswer ? "Редактирование ответа" : "Новый ответ"?></div>
            <div class="adm-detail-content-item-block">
                <form method="POST">
                    <?=bitrix_sessid_post()?>
                    <input type="hidden" name="WEB_FORM_ID" value="<?=$WEB_FORM_ID?>">
                    <input type="hidden" name="RESULT_ID" value="<?=$RESULT_ID?>">
                    <input type="hidden" name="save" value="Y">
                    <?if($editAnswer):?>
                        <input type="hidden" name="edit_id" value="<?=$editAnswer["ID"]?>">
                    <?endif;?>
                    
                    <textarea name="ANSWER" id="ANSWER" style="width:100%;height:400px;"></textarea>
                    <input type="hidden" name="ANSWER_TYPE" id="ANSWER_TYPE" value="html">
                    
                    <div style="margin-top:10px;">
                        <input type="submit" value="<?=$editAnswer ? "Обновить" : "Сохранить"?>" class="adm-btn-save">
                        <?if($editAnswer):?>
                            <a href="?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID=<?=$RESULT_ID?>" class="adm-btn">Отмена</a>
                        <?endif;?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="adm-detail-content-wrap" style="margin-top:20px;">
        <div class="adm-detail-content">
            <div class="adm-detail-title">Существующие ответы</div>
            <div class="adm-detail-content-item-block">
                <?
                $iblockId = CFormAnswers::getAnswersIBlockId();
                if ($iblockId > 0)
                {
                    $res = CIBlockElement::GetList(
                        array("ID" => "DESC"),
                        array(
                            "IBLOCK_ID" => $iblockId,
                            "ACTIVE" => "Y",
                            "PROPERTY_ID_RESULT" => $RESULT_ID,
                            "PROPERTY_ID_FORM" => $WEB_FORM_ID,
                        ),
                        false,
                        false,
                        array("ID", "NAME", "DETAIL_TEXT", "DETAIL_TEXT_TYPE", "DATE_CREATE")
                    );
                    
                    $hasAnswers = false;
                    while ($answer = $res->GetNext())
                    {
                        $hasAnswers = true;
                        ?>
                        <div style="border:1px solid #ddd; padding:15px; margin-bottom:10px; background:#f9f9f9;">
                            <div style="margin-bottom:10px; color:#666;">
                                <?=$answer["DATE_CREATE"]?>
                                <a href="?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID=<?=$RESULT_ID?>&edit_id=<?=$answer["ID"]?>" 
                                   style="margin-left:10px;">Редактировать</a>
                                <a href="?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID=<?=$RESULT_ID?>&action=delete&delete_id=<?=$answer["ID"]?>&<?=bitrix_sessid_get()?>" 
                                   onclick="return confirm('Удалить?')" 
                                   style="margin-left:10px; color:red;">Удалить</a>
                            </div>
                            <div style="padding:10px; background:white; border:1px solid #e0e0e0;">
                                <?=($answer["DETAIL_TEXT_TYPE"] == "html" ? $answer["DETAIL_TEXT"] : nl2br(htmlspecialcharsbx($answer["DETAIL_TEXT"])))?>
                            </div>
                        </div>
                        <?
                    }
                    if (!$hasAnswers)
                        echo "<p>Нет ответов</p>";
                }
                ?>
            </div>
        </div>
    </div>
<?endif;?>

<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
?>