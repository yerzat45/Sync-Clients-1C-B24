<?php

// Конфигурация
$config = [
    'api_url' => 'api_url',
    'username' => 'username',
    'password' => 'password',
    'bitrix_webhook_url' => 'https://crm.xxx.kz/xxx/xxx/xxx/',
    'timezone' => 'Asia/Almaty'
];

// Устанавливаем временную зону
date_default_timezone_set($config['timezone']);

// Создаем уникальное имя для лог-файла и JSON-файла
$currentDateTime = date('Y-m-d_H-i-s');
$logFileName = __DIR__ . '/log_' . $currentDateTime . '.txt';
$jsonFileName = __DIR__ . '/response_' . $currentDateTime . '.txt';

// Функция для логирования
function logMessage($message, $logFile) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Логируем запуск скрипта
logMessage("Скрипт запущен.", $logFileName);

try {
    // Логируем начало загрузки данных
    logMessage("Начало загрузки данных из API.", $logFileName);

    // Загрузка данных из API
    $ch = curl_init($config['api_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "{$config['username']}:{$config['password']}");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    logMessage("if", $logFileName);

    if (curl_errno($ch)) {
        throw new Exception("Ошибка cURL: " . curl_error($ch));
    } elseif ($http_code !== 200) {
        throw new Exception("Ошибка HTTP: $http_code\nОтвет сервера: $response");
    }

    curl_close($ch);

	logMessage("http_code=".$http_code, $logFileName);
    // Логируем завершение загрузки данных
    logMessage("Данные успешно загружены.", $logFileName);

    // Преобразуем кодировку и сохраняем ответ
    $responseUtf8 = iconv('Windows-1251', 'UTF-8//IGNORE', $response);
    $companiesData = json_decode($responseUtf8, true);

	logMessage("JSON", $logFileName);

    if (!is_array($companiesData) || empty($companiesData['body']) || !is_array($companiesData['body'])) {
        throw new Exception("Неверный формат JSON или пустые данные");
    }

    logMessage("JSON OK", $logFileName);

    // Ограничиваем обработку первыми 100 компаниями
	$companies = array_slice($companiesData['body'], 00, 100);

    $currentDateTime = (new DateTime('now'))->format('Y-m-d H:i:s');
    $totalCompanies = count($companies);
    $updatedCount = 0;
    $createdCount = 0;
    $failedCompanies = [];

    // Логируем начало обработки компаний
    logMessage("Начало обработки компаний. Всего компаний: $totalCompanies.", $logFileName);

    foreach ($companies as $index => $company) {
        if (!isset($company['GUID']) || empty($company['GUID'])) {
            logMessage("Пропуск компании #" . ($index + 1) . ": отсутствует GUID.", $logFileName);
            $failedCompanies[] = $company;
            continue;
        }

        $guid = $company['GUID'];
        $inn = $company['ИНН'] ?? '';

        try {
            if (!empty($inn)) {
                // Поиск компании в Bitrix24
                $searchResult = callBitrix24Method('crm.company.list', [
                    'filter' => ['UF_ID_1C' => $guid],
                    'select' => ['ID']
                ], $config['bitrix_webhook_url']);

                if (!empty($searchResult) && isset($searchResult[0]['ID'])) {
                    // Обновление компании
                    $companyId = $searchResult[0]['ID'];
                    $updateResult = callBitrix24Method('crm.company.update', [
                        'id' => $companyId,
                        'fields' => [
                            'TITLE' => $company['Наименование'] ?? '',
                            'UF_COD_INN' => $inn,
                            'UF_CRM_1740661781' => $currentDateTime
                        ]
                    ], $config['bitrix_webhook_url']);

                    if ($updateResult) {
                        $updatedCount++;
                        logMessage("Компания #" . ($index + 1) . " (GUID: $guid) успешно обновлена.", $logFileName);
                    } else {
                        logMessage("Ошибка обновления компании #" . ($index + 1) . " (GUID: $guid).", $logFileName);
                        $failedCompanies[] = $company;
                    }
                } else {
                    // Создание новой компании
                    $createResult = callBitrix24Method('crm.company.add', [
                        'fields' => [
                            'TITLE' => $company['Наименование'] ?? '',
                            'UF_ID_1C' => $guid,
                            'UF_COD_INN' => $inn,
                            'UF_CRM_1740661781' => $currentDateTime
                        ]
                    ], $config['bitrix_webhook_url']);

                    if ($createResult) {
                        $createdCount++;
                        logMessage("Компания #" . ($index + 1) . " (GUID: $guid) успешно создана.", $logFileName);
                    } else {
                        logMessage("Ошибка создания компании #" . ($index + 1) . " (GUID: $guid).", $logFileName);
                        $failedCompanies[] = $company;
                    }
                }
            } else {
                // Обработка контактов (если ИНН отсутствует)
                $searchResult = callBitrix24Method('crm.contact.list', [
                    'filter' => ['UF_ID_1C' => $guid],
                    'select' => ['ID']
                ], $config['bitrix_webhook_url']);

                if (!empty($searchResult) && isset($searchResult[0]['ID'])) {
                    // Обновление контакта
                    $contactId = $searchResult[0]['ID'];
                    callBitrix24Method('crm.contact.update', [
                        'id' => $contactId,
                        'fields' => [
                            'NAME' => $company['Наименование'] ?? '',
                            'UF_CRM_1740728813' => $currentDateTime
                        ]
                    ], $config['bitrix_webhook_url']);
                    $updatedCount++;
                    logMessage("Контакт #" . ($index + 1) . " (GUID: $guid) успешно обновлен.", $logFileName);
                } else {
                    // Создание нового контакта
                    callBitrix24Method('crm.contact.add', [
                        'fields' => [
                            'NAME' => $company['Наименование'] ?? '',
                            'UF_ID_1C' => $guid,
                            'UF_CRM_1740728813' => $currentDateTime
                        ]
                    ], $config['bitrix_webhook_url']);
                    $createdCount++;
                    logMessage("Контакт #" . ($index + 1) . " (GUID: $guid) успешно создан.", $logFileName);
                }
            }
        } catch (Exception $e) {
            logMessage("Ошибка обработки компании #" . ($index + 1) . " (GUID: $guid): " . $e->getMessage(), $logFileName);
            $failedCompanies[] = $company;
        }

        // Задержка между запросами (1 секунда)
        sleep(1);
    }

    // Логируем завершение обработки
    logMessage("Обработка завершена. Всего компаний: $totalCompanies, обновлено: $updatedCount, создано: $createdCount, ошибок: " . count($failedCompanies), $logFileName);

    // Формируем итоговый ответ
    $response = [
        "result" => "success",
        "code" => 1,
        "body" => [
            "всего_компаний" => $totalCompanies,
            "обновлено" => $updatedCount,
            "создано" => $createdCount,
            "ошибок" => count($failedCompanies),
            "список_ошибок" => $failedCompanies
        ]
    ];

    // Сохраняем JSON-ответ в файл
    $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($jsonFileName, $jsonResponse);

    // Выводим JSON-ответ
    echo $jsonResponse;

} catch (Exception $e) {
    // Логируем критическую ошибку
    logMessage("Критическая ошибка: " . $e->getMessage(), $logFileName);
    echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// Логируем завершение работы скрипта
logMessage("Скрипт завершен.", $logFileName);

// Функция для вызова методов Bitrix24
function callBitrix24Method($method, $params = [], $webhookUrl) {
    $url = $webhookUrl . $method . '.json';
    $queryData = http_build_query($params);
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_POST => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $queryData,
    ]);
    $result = curl_exec($curl);
    if (curl_errno($curl)) {
        throw new Exception('Ошибка cURL: ' . curl_error($curl));
    }
    curl_close($curl);
    $decodedResult = json_decode($result, true);

    // Обработка ошибки "Too many requests"
    if (isset($decodedResult['error']) && $decodedResult['error'] === 'QUERY_LIMIT_EXCEEDED') {
        sleep(10); // Пауза 10 секунд
        return callBitrix24Method($method, $params, $webhookUrl); // Повторный запрос
    }

    if (isset($decodedResult['error'])) {
        throw new Exception('Ошибка Bitrix24 API: ' . $decodedResult['error_description']);
    }

    return $decodedResult['result'];
}
