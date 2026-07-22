<?php
/**
 * Завершение установки модуля form.answers
 * Исправляет ошибки с правами инфоблока
 */

$DBHost = 'localhost';
$DBName = 'sitemanager';
$DBLogin = 'bitrix';
$DBPassword = 'x897ty';

$DOCUMENT_ROOT = '/var/www/bitrix_site';

echo "=== Завершение установки модуля form.answers ===\n\n";

try {
    $mysqli = new mysqli($DBHost, $DBLogin, $DBPassword, $DBName);
    
    if ($mysqli->connect_error) {
        throw new Exception("Ошибка подключения к БД: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset("utf8");
    echo "1. Подключение к БД: OK\n";
    
    // Проверяем, что модуль зарегистрирован
    $result = $mysqli->query("SELECT ID FROM b_module WHERE ID = 'form.answers'");
    if ($result->num_rows == 0) {
        $mysqli->query("INSERT INTO b_module (ID, DATE_ACTIVE) VALUES ('form.answers', NOW())");
        echo "2. Модуль зарегистрирован: OK\n";
    } else {
        echo "2. Модуль уже зарегистрирован\n";
    }
    
    // Проверяем структуру таблицы b_iblock_right
    $result = $mysqli->query("DESCRIBE b_iblock_right");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    echo "3. Структура таблицы прав:\n";
    echo "   Доступные поля: " . implode(', ', $columns) . "\n";
    
    // Получаем или создаем инфоблок
    $iblockId = 0;
    $result = $mysqli->query("SELECT VALUE FROM b_option WHERE MODULE_ID = 'form.answers' AND NAME = 'answers_iblock_id'");
    if ($row = $result->fetch_assoc()) {
        $iblockId = intval($row['VALUE']);
    }
    
    if ($iblockId > 0) {
        $result = $mysqli->query("SELECT ID FROM b_iblock WHERE ID = {$iblockId}");
        if ($result->num_rows > 0) {
            echo "4. Инфоблок существует (ID: {$iblockId})\n";
        } else {
            $iblockId = 0;
            echo "4. Инфоблок не найден, создаем новый\n";
        }
    }
    
    // Создаем инфоблок если нужно
    if ($iblockId == 0) {
        // Проверяем тип инфоблока
        $result = $mysqli->query("SELECT ID FROM b_iblock_type WHERE ID = 'form_answers'");
        if ($result->num_rows == 0) {
            $mysqli->query("INSERT INTO b_iblock_type (ID, SECTIONS, IN_RSS, SORT) VALUES ('form_answers', 'N', 'N', 500)");
            $mysqli->query("INSERT INTO b_iblock_type_lang (IBLOCK_TYPE_ID, LID, NAME, SECTION_NAME, ELEMENT_NAME) VALUES ('form_answers', 'ru', 'Ответы на формы', '', 'Ответ')");
            echo "   - Тип инфоблока создан\n";
        }
        
        // Создаем инфоблок
        $xmlId = md5(uniqid(rand(), true));
        
        $mysqli->query("
            INSERT INTO b_iblock (
                TIMESTAMP_X, IBLOCK_TYPE_ID, LID, CODE, NAME, ACTIVE, SORT,
                LIST_PAGE_URL, DETAIL_PAGE_URL, SECTION_PAGE_URL, CANONICAL_PAGE_URL,
                PICTURE, DESCRIPTION, DESCRIPTION_TYPE, RSS_TTL, RSS_ACTIVE,
                XML_ID, TMP_ID, INDEX_ELEMENT, INDEX_SECTION, WORKFLOW,
                BIZPROC, SECTION_CHOOSER, LIST_MODE, RIGHTS_MODE, SECTION_PROPERTY,
                PROPERTY_INDEX, VERSION, LAST_CONV_ELEMENT
            ) VALUES (
                NOW(), 'form_answers', 's1', 'form_answers', 'Ответы на результаты форм',
                'Y', 500,
                '', '', '', '',
                NULL, '', 'text', 0, 'N',
                '{$xmlId}', 0, 'Y', 'Y', 'N',
                'N', 'L', 'C', 'E', 'N',
                'Y', 2, 0
            )
        ");
        
        $iblockId = $mysqli->insert_id;
        
        if ($iblockId) {
            // Сохраняем ID инфоблока
            $mysqli->query("UPDATE b_option SET VALUE = '{$iblockId}' WHERE MODULE_ID = 'form.answers' AND NAME = 'answers_iblock_id'");
            
            // Создаем свойства
            $xmlProp1 = md5(uniqid(rand(), true));
            $xmlProp2 = md5(uniqid(rand(), true));
            
            $mysqli->query("
                INSERT INTO b_iblock_property (
                    IBLOCK_ID, NAME, ACTIVE, SORT, CODE, DEFAULT_VALUE,
                    PROPERTY_TYPE, ROW_COUNT, COL_COUNT, LIST_TYPE, MULTIPLE,
                    XML_ID, FILE_TYPE, MULTIPLE_CNT, TMP_ID, LINK_IBLOCK_ID,
                    WITH_DESCRIPTION, SEARCHABLE, FILTRABLE, IS_REQUIRED, VERSION
                ) VALUES (
                    {$iblockId}, 'ID результата', 'Y', 100, 'ID_RESULT', '',
                    'N', 1, 30, 'L', 'N',
                    '{$xmlProp1}', '', 5, 0, 0,
                    'N', 'N', 'Y', 'Y', 2
                )
            ");
            
            $mysqli->query("
                INSERT INTO b_iblock_property (
                    IBLOCK_ID, NAME, ACTIVE, SORT, CODE, DEFAULT_VALUE,
                    PROPERTY_TYPE, ROW_COUNT, COL_COUNT, LIST_TYPE, MULTIPLE,
                    XML_ID, FILE_TYPE, MULTIPLE_CNT, TMP_ID, LINK_IBLOCK_ID,
                    WITH_DESCRIPTION, SEARCHABLE, FILTRABLE, IS_REQUIRED, VERSION
                ) VALUES (
                    {$iblockId}, 'ID формы', 'Y', 200, 'ID_FORM', '',
                    'N', 1, 30, 'L', 'N',
                    '{$xmlProp2}', '', 5, 0, 0,
                    'N', 'N', 'Y', 'Y', 2
                )
            ");
            
            // Привязываем к сайту
            $mysqli->query("INSERT INTO b_iblock_site (IBLOCK_ID, SITE_ID) VALUES ({$iblockId}, 's1')");
            
            echo "4. Инфоблок создан (ID: {$iblockId})\n";
            echo "5. Свойства созданы\n";
        } else {
            throw new Exception("Не удалось создать инфоблок");
        }
    }
    
    // Устанавливаем права на инфоблок (АДАПТИВНО)
    echo "6. Установка прав на инфоблок:\n";
    
    // Удаляем старые права если есть
    $mysqli->query("DELETE FROM b_iblock_right WHERE IBLOCK_ID = {$iblockId}");
    
    // Строим запрос в зависимости от структуры таблицы
    $fields = ['IBLOCK_ID', 'GROUP_CODE', 'TASK_ID'];
    $hasDoClean = in_array('DO_CLEAN', $columns);
    
    if ($hasDoClean) {
        $fields[] = 'DO_CLEAN';
        $sql = "INSERT INTO b_iblock_right (" . implode(', ', $fields) . ") VALUES ({$iblockId}, 'G1', 'N', 0)";
    } else {
        $sql = "INSERT INTO b_iblock_right (" . implode(', ', $fields) . ") VALUES ({$iblockId}, 'G1', 0)";
    }
    
    try {
        $mysqli->query($sql);
        echo "   - Права для группы 1: OK\n";
    } catch (Exception $e) {
        echo "   - Ошибка прав G1: " . $e->getMessage() . "\n";
        
        // Пробуем минимальный вариант
        try {
            $mysqli->query("INSERT INTO b_iblock_right (IBLOCK_ID, GROUP_CODE) VALUES ({$iblockId}, 'G1')");
            echo "   - Права для группы 1 (мин): OK\n";
        } catch (Exception $e2) {
            echo "   - Не удалось установить права G1\n";
        }
    }
    
    if ($hasDoClean) {
        $sql2 = "INSERT INTO b_iblock_right (" . implode(', ', $fields) . ") VALUES ({$iblockId}, 'G2', 'N', 30)";
    } else {
        $sql2 = "INSERT INTO b_iblock_right (" . implode(', ', $fields) . ") VALUES ({$iblockId}, 'G2', 30)";
    }
    
    try {
        $mysqli->query($sql2);
        echo "   - Права для группы 2: OK\n";
    } catch (Exception $e) {
        echo "   - Ошибка прав G2: " . $e->getMessage() . "\n";
        
        try {
            $mysqli->query("INSERT INTO b_iblock_right (IBLOCK_ID, GROUP_CODE) VALUES ({$iblockId}, 'G2')");
            echo "   - Права для группы 2 (мин): OK\n";
        } catch (Exception $e2) {
            echo "   - Не удалось установить права G2\n";
        }
    }
    
    // Проверяем и добавляем недостающие файлы админки
    echo "7. Проверка файлов админки:\n";
    $adminFiles = [
        'form_answers_admin.php',
        'form_answers_settings.php'
    ];
    
    foreach ($adminFiles as $file) {
        $sourcePath = $DOCUMENT_ROOT . '/bitrix/modules/form.answers/admin/' . $file;
        $targetPath = $DOCUMENT_ROOT . '/bitrix/admin/' . $file;
        
        if (file_exists($sourcePath)) {
            if (!file_exists($targetPath) || filesize($targetPath) != filesize($sourcePath)) {
                copy($sourcePath, $targetPath);
                echo "   - {$file}: скопирован\n";
            } else {
                echo "   - {$file}: на месте\n";
            }
        } else {
            echo "   - {$file}: отсутствует в модуле!\n";
        }
    }
    
    // Очищаем кэш
    echo "8. Очистка кэша:\n";
    $cacheDirs = [
        $DOCUMENT_ROOT . '/bitrix/cache',
        $DOCUMENT_ROOT . '/bitrix/managed_cache'
    ];
    
    foreach ($cacheDirs as $dir) {
        if (is_dir($dir)) {
            $count = 0;
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                @$todo($fileinfo->getRealPath());
                $count++;
            }
            echo "   - " . basename($dir) . ": удалено {$count} файлов\n";
        }
    }
    
    $mysqli->close();
    
    echo "\n✅ УСТАНОВКА ЗАВЕРШЕНА!\n\n";
    echo "========================================\n";
    echo "Модуль: Ответы на результаты веб-форм\n";
    echo "ID инфоблока: {$iblockId}\n";
    echo "========================================\n\n";
    echo "Дальнейшие шаги:\n";
    echo "1. Зайдите в админку сайта\n";
    echo "2. Перейдите: Настройки → Настройки модулей → Ответы на результаты веб-форм\n";
    echo "3. Отметьте галочками нужные веб-формы\n";
    echo "4. Сохраните настройки\n";
    echo "5. Перейдите в результаты форм - там появится вкладка \"Ответы\"\n\n";
    echo "Для вывода ответов на сайте используйте:\n";
    echo "CFormAnswers::getAnswers(\$resultId, \$formId);\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    if (isset($mysqli)) {
        echo "SQL: " . $mysqli->error . "\n";
    }
    exit(1);
}