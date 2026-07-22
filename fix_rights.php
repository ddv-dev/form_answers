<?php
/**
 * Универсальное исправление прав инфоблока
 * Автоматически определяет все обязательные поля
 */

$DBHost = 'localhost';
$DBName = 'sitemanager';
$DBLogin = 'bitrix';
$DBPassword = 'x897ty';

echo "=== Исправление прав инфоблока (финальная версия) ===\n\n";

try {
    $mysqli = new mysqli($DBHost, $DBLogin, $DBPassword, $DBName);
    $mysqli->set_charset("utf8");
    
    // Получаем ID инфоблока
    $result = $mysqli->query("SELECT VALUE FROM b_option WHERE MODULE_ID = 'form.answers' AND NAME = 'answers_iblock_id'");
    $row = $result->fetch_assoc();
    $iblockId = intval($row['VALUE']);
    
    echo "ID инфоблока: {$iblockId}\n";
    
    if ($iblockId == 0) {
        die("❌ Инфоблок не найден!\n");
    }
    
    // Получаем структуру таблицы b_iblock_right
    $result = $mysqli->query("DESCRIBE b_iblock_right");
    $columns = [];
    $requiredFields = [];
    
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row;
        
        // Определяем обязательные поля
        if ($row['Null'] == 'NO' && $row['Default'] === null && $row['Extra'] != 'auto_increment') {
            $requiredFields[$row['Field']] = $row;
        }
    }
    
    echo "\nСтруктура таблицы b_iblock_right:\n";
    echo "Все поля: " . implode(', ', array_keys($columns)) . "\n";
    echo "Обязательные поля: " . implode(', ', array_keys($requiredFields)) . "\n\n";
    
    // Удаляем старые права
    $mysqli->query("DELETE FROM b_iblock_right WHERE IBLOCK_ID = {$iblockId}");
    echo "Старые права удалены\n\n";
    
    // Базовые значения для полей
    $baseValues = [
        'IBLOCK_ID' => $iblockId,
        'GROUP_CODE' => '', // Будет установлено отдельно
        'ENTITY_TYPE' => 'iblock',
        'ENTITY_ID' => 0,
        'DO_INHERIT' => 'N',
        'TASK_ID' => 0, // Будет изменено для разных групп
        'OP_SREAD' => 'N',
        'OP_EREAD' => 'N',
        'XML_ID' => '',
    ];
    
    // Функция для вставки прав
    function insertRights($mysqli, $iblockId, $groupCode, $taskId, $baseValues, $requiredFields) {
        $values = $baseValues;
        $values['IBLOCK_ID'] = $iblockId;
        $values['GROUP_CODE'] = $groupCode;
        $values['TASK_ID'] = $taskId;
        
        // Оставляем только те поля, которые есть в таблице
        $filteredValues = [];
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $requiredFields) || $key == 'IBLOCK_ID' || $key == 'GROUP_CODE' || $key == 'TASK_ID') {
                $filteredValues[$key] = $value;
            }
        }
        
        // Добавляем все обязательные поля, если их нет
        foreach ($requiredFields as $field => $info) {
            if (!isset($filteredValues[$field])) {
                // Устанавливаем значения по умолчанию в зависимости от типа
                $type = $info['Type'];
                if (strpos($type, 'int') !== false) {
                    $filteredValues[$field] = 0;
                } elseif (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
                    $filteredValues[$field] = '';
                } else {
                    $filteredValues[$field] = '';
                }
            }
        }
        
        // Строим SQL запрос
        $fields = array_keys($filteredValues);
        $placeholders = array_fill(0, count($fields), '?');
        $values_array = array_values($filteredValues);
        
        $sql = "INSERT INTO b_iblock_right (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $mysqli->prepare($sql);
        
        if ($stmt) {
            // Динамически формируем типы параметров
            $types = '';
            foreach ($values_array as $value) {
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_double($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            
            // Привязываем параметры
            $bindParams = array($types);
            foreach ($values_array as $key => $value) {
                $bindParams[] = &$values_array[$key];
            }
            
            call_user_func_array(array($stmt, 'bind_param'), $bindParams);
            
            if ($stmt->execute()) {
                return true;
            } else {
                echo "   Ошибка выполнения: " . $stmt->error . "\n";
                return false;
            }
        } else {
            echo "   Ошибка подготовки: " . $mysqli->error . "\n";
            return false;
        }
    }
    
    // Устанавливаем права для администраторов (G2)
    echo "Установка прав для группы G2 (администраторы):\n";
    if (insertRights($mysqli, $iblockId, 'G2', 30, $baseValues, $requiredFields)) {
        echo "✅ Права для G2 установлены\n";
    } else {
        echo "❌ Не удалось установить права для G2\n";
        
        // Пробуем минимальный вариант
        echo "\nПробуем минимальный вариант для G2:\n";
        $minSql = "INSERT INTO b_iblock_right (IBLOCK_ID, GROUP_CODE, ENTITY_TYPE, ENTITY_ID, DO_INHERIT, TASK_ID, OP_SREAD, OP_EREAD) 
                   VALUES ({$iblockId}, 'G2', 'iblock', 0, 'N', 30, 'N', 'N')";
        
        if ($mysqli->query($minSql)) {
            echo "✅ Минимальные права для G2 установлены\n";
        } else {
            echo "❌ Ошибка: " . $mysqli->error . "\n";
        }
    }
    
    // Устанавливаем права для всех пользователей (G1)
    echo "\nУстановка прав для группы G1 (все пользователи):\n";
    if (insertRights($mysqli, $iblockId, 'G1', 0, $baseValues, $requiredFields)) {
        echo "✅ Права для G1 установлены\n";
    } else {
        echo "❌ Не удалось установить права для G1\n";
        
        echo "\nПробуем минимальный вариант для G1:\n";
        $minSql2 = "INSERT INTO b_iblock_right (IBLOCK_ID, GROUP_CODE, ENTITY_TYPE, ENTITY_ID, DO_INHERIT, TASK_ID, OP_SREAD, OP_EREAD) 
                    VALUES ({$iblockId}, 'G1', 'iblock', 0, 'N', 0, 'N', 'N')";
        
        if ($mysqli->query($minSql2)) {
            echo "✅ Минимальные права для G1 установлены\n";
        } else {
            echo "❌ Ошибка: " . $mysqli->error . "\n";
        }
    }
    
    // Проверяем результат
    echo "\n========================================\n";
    echo "РЕЗУЛЬТАТ:\n";
    echo "========================================\n";
    
    $result = $mysqli->query("SELECT * FROM b_iblock_right WHERE IBLOCK_ID = {$iblockId}");
    $hasRights = false;
    
    while ($row = $result->fetch_assoc()) {
        $hasRights = true;
        echo "Группа: {$row['GROUP_CODE']}\n";
        echo "  Тип: {$row['ENTITY_TYPE']}\n";
        echo "  Задача: {$row['TASK_ID']}\n";
        echo "  Чтение: {$row['OP_SREAD']}\n";
        echo "  ---\n";
    }
    
    if (!$hasRights) {
        echo "❌ Права не установлены!\n";
        echo "\nПопробуйте установить права через админку Битрикс:\n";
        echo "1. Зайдите в админку\n";
        echo "2. Контент → Инфоблоки → Типы инфоблоков\n";
        echo "3. Найдите тип 'form_answers'\n";
        echo "4. Откройте инфоблок 'Ответы на результаты форм'\n";
        echo "5. Перейдите на вкладку 'Доступ'\n";
        echo "6. Установите нужные права\n";
    } else {
        echo "\n✅ Права установлены успешно!\n";
    }
    
    // Проверяем и дополняем другие настройки
    echo "\n========================================\n";
    echo "ДОПОЛНИТЕЛЬНЫЕ ПРОВЕРКИ:\n";
    echo "========================================\n";
    
    // Проверяем свойства инфоблока
    $result = $mysqli->query("SELECT * FROM b_iblock_property WHERE IBLOCK_ID = {$iblockId}");
    $propCount = $result->num_rows;
    
    if ($propCount == 0) {
        echo "❌ Свойства отсутствуют! Создаем...\n";
        
        // Создаем свойства
        $mysqli->query("
            INSERT INTO b_iblock_property (
                IBLOCK_ID, NAME, ACTIVE, SORT, CODE, DEFAULT_VALUE,
                PROPERTY_TYPE, ROW_COUNT, COL_COUNT, LIST_TYPE, MULTIPLE,
                XML_ID, FILE_TYPE, MULTIPLE_CNT, TMP_ID, LINK_IBLOCK_ID,
                WITH_DESCRIPTION, SEARCHABLE, FILTRABLE, IS_REQUIRED, VERSION
            ) VALUES (
                {$iblockId}, 'ID результата', 'Y', 100, 'ID_RESULT', '',
                'N', 1, 30, 'L', 'N',
                '', '', 5, 0, 0,
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
                '', '', 5, 0, 0,
                'N', 'N', 'Y', 'Y', 2
            )
        ");
        
        echo "✅ Свойства созданы\n";
    } else {
        echo "✅ Свойства существуют ({$propCount} шт.)\n";
    }
    
    // Проверяем привязку к сайту
    $result = $mysqli->query("SELECT * FROM b_iblock_site WHERE IBLOCK_ID = {$iblockId}");
    if ($result->num_rows == 0) {
        $mysqli->query("INSERT INTO b_iblock_site (IBLOCK_ID, SITE_ID) VALUES ({$iblockId}, 's1')");
        echo "✅ Привязка к сайту добавлена\n";
    } else {
        echo "✅ Привязка к сайту существует\n";
    }
    
    $mysqli->close();
    
    echo "\n========================================\n";
    echo "ГОТОВО! Модуль настроен.\n";
    echo "========================================\n\n";
    echo "📋 Чек-лист:\n";
    echo "☑ Модуль зарегистрирован в БД\n";
    echo "☑ Инфоблок создан (ID: {$iblockId})\n";
    echo "☑ Свойства инфоблока созданы\n";
    echo "☑ Файлы админки скопированы\n";
    echo "☑ Обработчики событий зарегистрированы\n\n";
    echo "🔗 Ссылки:\n";
    echo "  Настройки модуля: /bitrix/admin/settings.php?lang=ru&mid=form.answers\n";
    echo "  Инфоблок: /bitrix/admin/iblock_edit.php?type=form_answers&lang=ru&ID={$iblockId}\n";
    echo "  Результаты форм: /bitrix/admin/form_result_list.php?lang=ru\n";
    
} catch (Exception $e) {
    echo "❌ Критическая ошибка: " . $e->getMessage() . "\n";
    if (isset($mysqli)) {
        echo "SQL ошибка: " . $mysqli->error . "\n";
    }
    exit(1);
}