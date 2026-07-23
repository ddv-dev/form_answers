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
$bIframe = ($_REQUEST["IFRAME"] === "Y");

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
$saveError = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_REQUEST["save"] == "Y" && check_bitrix_sessid())
{
    $iblockId = CFormAnswers::getAnswersIBlockId();
    $answerText = isset($_REQUEST["ANSWER"]) ? $_REQUEST["ANSWER"] : "";

    // Проверка на пустой ответ: убираем HTML-теги, неразрывные и обычные
    // пробелы - если ничего значимого не осталось, сохранять нечего.
    $answerPlain = trim(str_replace(array("&nbsp;", "\xC2\xA0"), " ", strip_tags($answerText)));

    if ($answerPlain === "")
    {
        $saveError = "Нельзя сохранить пустой ответ - введите текст.";
    }
    elseif ($iblockId > 0 && $RESULT_ID > 0)
    {
        $el = new CIBlockElement;

        $arFields = array(
            "IBLOCK_ID" => $iblockId,
            "NAME" => "Ответ на результат #".$RESULT_ID." (форма #".$WEB_FORM_ID.")",
            "ACTIVE" => "Y",
            "DETAIL_TEXT" => $answerText,
            "DETAIL_TEXT_TYPE" => $_REQUEST["ANSWER_TYPE"] ?: "html",
            "PROPERTY_VALUES" => array(
                "ID_RESULT" => $RESULT_ID,
                "ID_FORM" => $WEB_FORM_ID,
            ),
        );

        $edit_id = intval($_REQUEST["edit_id"]);

        if ($edit_id > 0)
            $ok = $el->Update($edit_id, $arFields);
        else
            $ok = $el->Add($arFields);

        if ($ok)
            LocalRedirect($APPLICATION->GetCurPageParam("", array("edit_id", "save", "sessid")));
        else
            $saveError = "Ошибка сохранения: ".$el->LAST_ERROR;
    }
    else
    {
        $saveError = "Не удалось определить инфоблок ответов или результат формы.";
    }
}

// Список результатов
$rsResults = CFormResult::GetList($WEB_FORM_ID, "s_id", "desc", array());
$arResults = array();
while ($arResult = $rsResults->Fetch())
    $arResults[] = $arResult;

// Редактирование
$ifr = $bIframe ? "&IFRAME=Y" : "";

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

// Всегда рендерим как полноценную админ-страницу: это нужно, чтобы штатный
// HTML-редактор Битрикса (CFileMan::AddHTMLEditorFrame) корректно
// инициализировался - его JS дорисовывается именно в админ-эпилоге.
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");

if ($bIframe):
    // Страница открыта в iframe во вкладке "Ответы" - прячем меню и шапку
    // админки, оставляя только содержимое.
?>
<style>
    #bx_menu_panel, .adm-left-side-wrap, .adm-left-side,
    .adm-header, .adm-header-container, #bx_header, #bx_panel, #panel,
    .adm-toolbar-panel-container, .adm-nav-corner,
    .adm-footer, #footer { display: none !important; }
    .adm-workarea, #workarea { margin: 0 !important; padding: 0 !important; }
    html, body#bx-admin-prefix { background: #fff !important; min-width: 0 !important; }
    body#bx-admin-prefix { padding: 12px !important; }
</style>
<?
endif;
?>

<div class="adm-detail-content-wrap">
    <div class="adm-detail-content">
        <div class="adm-detail-title">Выберите результат</div>
        <div class="adm-detail-content-item-block">
            <select onchange="if(this.value) window.location='?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID='+this.value+'<?=$ifr?>'">
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
                <?if(!empty($saveError)):?>
                    <?=CAdminMessage::ShowMessage(array("MESSAGE" => $saveError, "TYPE" => "ERROR"))?>
                <?endif;?>
                <form method="POST">
                    <?=bitrix_sessid_post()?>
                    <input type="hidden" name="WEB_FORM_ID" value="<?=$WEB_FORM_ID?>">
                    <input type="hidden" name="RESULT_ID" value="<?=$RESULT_ID?>">
                    <input type="hidden" name="save" value="Y">
                    <?if($editAnswer):?>
                        <input type="hidden" name="edit_id" value="<?=$editAnswer["ID"]?>">
                    <?endif;?>
                    
                    <?
                    // Штатный визуальный редактор Битрикса (вкладки Визуальный / HTML / Текст).
                    // Сам создаёт поля ANSWER и ANSWER_TYPE и на submit формы переносит
                    // содержимое в textarea ANSWER.
                    if (CModule::IncludeModule("fileman"))
                    {
                        CFileMan::AddHTMLEditorFrame(
                            "ANSWER",
                            $editAnswer ? $editAnswer["~DETAIL_TEXT"] : "",
                            "ANSWER_TYPE",
                            $editAnswer ? $editAnswer["DETAIL_TEXT_TYPE"] : "html",
                            array("height" => 350, "width" => "100%")
                        );
                    }
                    else
                    {
                        // Фолбэк на случай, если модуль fileman недоступен.
                        ?>
                        <textarea name="ANSWER" style="width:100%;height:350px;"><?=htmlspecialcharsbx($editAnswer ? $editAnswer["~DETAIL_TEXT"] : "")?></textarea>
                        <input type="hidden" name="ANSWER_TYPE" value="html">
                        <?
                    }
                    ?>

                    <div style="margin-top:10px;">
                        <input type="submit" value="<?=$editAnswer ? "Обновить" : "Сохранить"?>" class="adm-btn-save">
                        <?if($editAnswer):?>
                            <a href="?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID=<?=$RESULT_ID?><?=$ifr?>" class="adm-btn">Отмена</a>
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
                                <a href="?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID=<?=$RESULT_ID?>&edit_id=<?=$answer["ID"]?><?=$ifr?>"
                                   style="margin-left:10px;">Редактировать</a>
                                <a href="?WEB_FORM_ID=<?=$WEB_FORM_ID?>&RESULT_ID=<?=$RESULT_ID?>&action=delete&delete_id=<?=$answer["ID"]?><?=$ifr?>&<?=bitrix_sessid_get()?>"
                                   onclick="return confirm('Удалить?')" 
                                   style="margin-left:10px; color:red;">Удалить</a>
                            </div>
                            <div style="padding:10px; background:white; border:1px solid #e0e0e0;">
                                <?=($answer["DETAIL_TEXT_TYPE"] == "html" ? $answer["~DETAIL_TEXT"] : nl2br(htmlspecialcharsbx($answer["~DETAIL_TEXT"])))?>
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