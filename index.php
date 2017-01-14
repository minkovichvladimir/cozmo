<?php
/**
 * Получает данные аналитики от Google и отправляет их сообщением в Slack
 *
 * @author Vladimir Minkovich
 * @date 13.01.17
 * @time 19:29
 */
require_once __DIR__ . '/classes/google/GoogleAnalytics.php';
require_once __DIR__ . '/classes/slack/Slack.php';

define('COZMOBET_KEY', '135942098');         //Ключ аккаунта cozmobet
define('COZMOSPORT_KEY', '126644071');       //Ключ аккаунта cozmosport

define('DAYS_COUNT', 1);                    //Кол-во дней за которые нужно получить отчет

define('USERS', 1);                         //Пользователи
define('PAGES', 2);                         //Просмотры страниц
define('USERS_COUNTRIES', 3);               //Пользователи по странам
define('TOP_PAGES', 4);                     //Самые популярные страницы
define('SESSIONS_TRAFFIC_REFERRAL', 5);     //Переходы
define('BOUNCE_RATE', 6);                   //Процент отказов
define('USERS_OS', 7);                      //Операционные системы
define('SESSIONS', 8);                      //Сеансы

//список ключей по которым будут выполняться запросы
$applicationsKeys =
    [
        COZMOBET_KEY,
        COZMOSPORT_KEY
    ];

//список запросов
$queryList = [
    USERS,
    PAGES,
    USERS_COUNTRIES,
    TOP_PAGES,
    SESSIONS_TRAFFIC_REFERRAL,
    BOUNCE_RATE,
    USERS_OS,
    SESSIONS
];

try {
    //проходим все указанные приложения
    foreach ($applicationsKeys as $applicationKey) {

        $google = new GoogleAnalytics($applicationKey);
        $analytics = $google->initializeAnalytics();

        $results = array();

        //проходим все необходимые запросы
        foreach ($queryList as $query) {
            $response = $google->getReport($analytics, $query, DAYS_COUNT);
            $results[$query] = $response;

            //$google->printResults($response);
        }

        if (isset($results)) {

            //подготовка результатов к отправке slack
            $normalizeResult = $google->prepareResultToSlackNotification($results, $applicationKey);

            //уведомление в slack
            if (isset($normalizeResult)) {
                $slack = new Slack();
                $applicationName = getApplicationName($applicationKey);
                $slack->sendNotification($normalizeResult, $applicationName);
            }
        }
    }

} catch (Exception $exception) {
    echo $exception->getMessage();
}

function getApplicationName($applicationKey)
{
    $dateNow = new DateTime('now');
    $dateFrom = strtotime('-' . DAYS_COUNT . ' day');
    switch ($applicationKey) {
        case COZMOBET_KEY: {
            $applicationName = 'cozmobet';
            break;
        }
        case COZMOSPORT_KEY: {
            $applicationName = 'cozmosport';
            break;
        }
        default: {
            $applicationName = '';
            break;
        }
    }

    return $applicationName . ' ' . date('d.m.Y', $dateFrom) . ' - ' . $dateNow->format('d.m.Y');
}



