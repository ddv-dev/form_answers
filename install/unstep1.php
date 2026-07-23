<?
// Шаг 1 деинсталляции: подтверждение и выбор - удалять ли инфоблок с ответами.
if(!check_bitrix_sessid()) return;
IncludeModuleLangFile(__FILE__);

global $APPLICATION;

echo CAdminMessage::ShowMessage(array(
    "TYPE" => "OK",
    "MESSAGE" => "Удаление модуля «Ответы на результаты веб-форм»",
    "DETAILS" => "По умолчанию инфоблок с ответами будет удалён вместе со всеми сохранёнными ответами.<br>"
        ."Отметьте галочку ниже, если нужно сохранить инфоблок и данные (например, перед переустановкой).",
    "HTML" => true,
));
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
    <?echo bitrix_sessid_post()?>
    <input type="hidden" name="lang" value="<?echo LANG?>">
    <input type="hidden" name="id" value="form.answers">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">

    <p>
        <label>
            <input type="checkbox" name="save_data" value="Y">
            Сохранить инфоблок с ответами (не удалять данные)
        </label>
    </p>

    <input type="submit" name="inst" value="Удалить модуль" class="adm-btn-save">
</form>
