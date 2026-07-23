<?
// Файл-сообщение об установке. Вызывается из DoInstall() через IncludeAdminFile,
// куда управление доходит только после проверки прав и сессии в module_admin.php,
// поэтому собственная проверка check_bitrix_sessid() здесь избыточна (и в контексте
// IncludeAdminFile мешает выводу сообщения).
IncludeModuleLangFile(__FILE__);

if($ex = $APPLICATION->GetException())
    echo CAdminMessage::ShowMessage(Array(
        "TYPE" => "ERROR",
        "MESSAGE" => GetMessage("MOD_INST_ERR"),
        "DETAILS" => $ex->GetString(),
        "HTML" => true,
    ));
else
    echo CAdminMessage::ShowNote(GetMessage("MOD_INST_OK"));
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
    <input type="hidden" name="lang" value="<?echo LANG?>">
    <input type="submit" name="" value="<?echo GetMessage("MOD_BACK")?>">
</form>