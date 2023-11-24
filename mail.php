<?php
date_default_timezone_set('Asia/Almaty');

function sendMessage($params) {
    $name = $params['name'];
    $phone = $params['phone'];
    $service = $params['service'];
    $utmSource = $params['utmSource'];
    $utmMedium = $params['utmMedium'];
    $utmCampaign = $params['utmCampaign'];
    $utmTerm = $params['utmTerm'];
    $gclid = $params['gclid'];
    $emailTo = $params['emailTo'];
    $telegramToken = $params['telegramToken'];
    $chatId = $params['chatId'];

    $message = "Имя: $name\n";
    $message .= "Телефон: $phone\n";
    $message .= "Форма: $service\n";

    try {
        if (mail($emailTo, "Новая заявка Mozz.kz", $message, "From: mail@mozz.kz\r\nReply-To: $emailTo\r\n")) {
            $ch = curl_init("https://api.telegram.org/bot$telegramToken/sendMessage");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'chat_id' => $chatId,
                'text' => $message,
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $responseData = json_decode($response, true);

            if ($responseData && $responseData['ok']) {
                // Отправка в Bitrix24
                $bitrixUrl = "https://b24-cpwi4w.bitrix24.kz/rest/6/t336e1m672pvxsqe/crm.lead.add.json";
                $params = [
                    'fields' => [
                        'TITLE' => 'Новая заявка с сайта Mozz.kz',
                        'SOURCE_ID' => 1, // Идентификатор источника в Битрикс24
                        'NAME' => $params['name'],
                        'UF_CRM_1699481973' => $params['service'], // Поле в Битрикс24, в которое будет записана услуга
                        'UTM_TERM' => $params['utmTerm'],
                        'UTM_SOURCE' => $params['utmSource'],
                        'UTM_MEDIUM' => $params['utmMedium'],
                        'SOURCE_ID' => 'WEB',
                        'UTM_CAMPAIGN' => $params['utmCampaign'],
                        "PHONE" => array(
                            array(
                                "VALUE" => $params['phone'],
                                "VALUE_TYPE" => "WORK"
                            )
                        ),
                    ],
                    'params' => [
                        'REGISTER_SONET_EVENT' => 'Y',
                    ],
                ];

                $ch = curl_init($bitrixUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $response = curl_exec($ch);
                curl_close($ch);



                $price = 0; //
                if (preg_match("/Базовый/i", $_POST["hiddenField"])) {
                $price = 250000;
                } elseif (preg_match("/Стандартный/i", $_POST["hiddenField"])) {
                $price = 1500000;
                } elseif (preg_match("/Средний/i", $_POST["hiddenField"])) {
                $price = 3400000;
                } elseif (preg_match("/VIP/i", $_POST["hiddenField"])) {
                $price = 6500000;
                }

                $conversionValue = $price;
                echo json_encode(["success" => $conversionValue]);
                
                // Добавляем запись в CSV-файл
                $csvFile = 'gclid.csv';
                // Используйте значение $price для создания строки в формате CSV
                $csvData = [
                    $_POST['gclid'], // Google Click ID
                    "CRM_Purchase", // Conversion Name
                    date("Y-m-d H:i:s T") . ' ' . 'Asia/Almaty', // Conversion Time в формате "2023-09-29 10:26:36 Asia/Almaty"
                    $price, // Conversion Value
                    "KZT" // Conversion Currency
                ];

                $csvData = implode(',', $csvData) . "\n";
                file_put_contents($csvFile, $csvData, FILE_APPEND);


                $responseData = json_decode($response, true);

                if ($responseData && isset($responseData['result']) && $responseData['result']) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $params = [
        'name' => $_POST["name"],
        'phone' => $_POST["phone"],
        'service' => $_POST["hiddenField"],
        'utmSource' => $_POST['utm_source'],
        'utmMedium' => $_POST['utm_medium'],
        'utmCampaign' => $_POST['utm_campaign'],
        'utmTerm' => $_POST['utm_term'],
        'gclid' => $_POST['gclid'],
        'emailTo' => "tokenov1@yandex.kz",
        'telegramToken' => "5126555813:AAFmpt2e_dpq8XujwRrjLmjcpdaQ8kUTiq0",
        'chatId' => "1475036403",
    ];

    // Проверка наличия обязательных полей
    if (empty($params['name']) || empty($params['phone'])) {
        http_response_code(400); // Неправильный запрос
        echo json_encode(["error" => "Имя и телефон обязательны для заполнения"]);
        exit;
    }

    $result = sendMessage($params);

    if ($result) {
        // Успешный ответ
        echo json_encode(["success" => "Заявка успешно отправлена"]);
    } else {
        // Ошибка отправки
        http_response_code(500); // Внутренняя ошибка сервера
        echo json_encode(["error" => "Произошла ошибка при отправке заявки"]);
    }
}