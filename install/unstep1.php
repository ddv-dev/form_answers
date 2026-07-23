<?
// Итоговое сообщение об удалении модуля.
if(!check_bitrix_sessid()) return;
IncludeModuleLangFile(__FILE__);

global $APPLICATION;

if($ex = $APPLICATION->GetException())
    echo CAdminMessage::ShowMessage(array(
        "TYPE" => "ERROR",
        "MESSAGE" => "Ошибка при удалении модуля",
        "DETAILS" => $ex->GetString(),
        "HTML" => true,
    ));
else
    echo CAdminMessage::ShowNote("Модуль «Ответы на результаты веб-форм» удалён.");
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
    <input type="hidden" name="lang" value="<?echo LANG?>">
    <input type="submit" name="" value="Вернуться в список модулей">
</form>
