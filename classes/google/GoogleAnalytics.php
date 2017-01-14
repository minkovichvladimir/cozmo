<?php

/**
 * ...
 *
 * @author Vladimir Minkovich
 * @date 13.01.17
 * @time 20:41
 */
require_once __DIR__ . '/../../vendor/autoload.php';

class GoogleAnalytics
{
    private $viewId = '';   // Идентификатор представления взят с https://ga-dev-tools.appspot.com/account-explorer/
    private $keyFileLocation = '';  //файл ключа к аккаунту (зависит от введенного $viewId)

    public function __construct($projectKey)
    {
        //берем нужный файл ключа для проекта
        if (isset($projectKey)) {
            if ($projectKey === COZMOBET_KEY) {
                $this->keyFileLocation = __DIR__ . '/../../keys/Cozmobet-01f9997cf6ad.json';
            } elseif ($projectKey === COZMOSPORT_KEY) {
                $this->keyFileLocation = __DIR__ . '/../../keys/Cozmosport-f4efecda3a5b.json';
            } else
                throw new Exception('Неверно указан viewId. Уточните на https://ga-dev-tools.appspot.com/account-explorer');

            $this->viewId = $projectKey;
        } else
            throw new Exception('Неверно указан viewId. Уточните на https://ga-dev-tools.appspot.com/account-explorer');
    }

    public function initializeAnalytics()
    {
        // Создание и настройка нового объекта клиента.
        $client = new Google_Client();
        $client->setApplicationName("Cozmo Reporting");
        $client->setAuthConfig($this->keyFileLocation);
        $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
        $analytics = new Google_Service_AnalyticsReporting($client);

        return $analytics;
    }

    public function getReport($analytics, $command, $days)
    {
        // Создание объекта DateRange.
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate($days . "daysAgo");
        $dateRange->setEndDate("today");

        // Создание объекта Metrics.
        $metric = new Google_Service_AnalyticsReporting_Metric();

        //Create the Dimensions object.
        $dimension = new Google_Service_AnalyticsReporting_Dimension();
        $dimensionUsing = false;    //флаг (dimensions может не использоваться)

        switch ($command) {
            case USERS: {
                $metric->setExpression("ga:users");
                $metric->setAlias("users");
                break;
            }
            case PAGES: {
                $metric->setExpression("ga:pageviews");
                $metric->setAlias("pageviews");
                break;
            }
            case USERS_COUNTRIES: {
                $metric->setExpression("ga:users");
                $metric->setAlias("users");
                $dimension->setName("ga:country");
                $dimensionUsing = true;
                break;
            }
            case TOP_PAGES: {
                $metric->setExpression("ga:pageviews");
                $metric->setAlias("pageviews");
                $dimension->setName("ga:pagePath");
                $dimensionUsing = true;
                break;
            }
            case SESSIONS_TRAFFIC_REFERRAL: {
                $metric->setExpression("ga:sessions");
                $metric->setAlias("sessions");
                $dimension->setName("ga:sourceMedium");
                $dimensionUsing = true;
                break;
            }
            case BOUNCE_RATE: {
                $metric->setExpression("ga:bounceRate");
                $metric->setAlias("bounce Rate");
                break;
            }
            case USERS_OS: {
                $metric->setExpression("ga:users");
                $metric->setAlias("users");
                $dimension->setName("ga:operatingSystem");
                $dimensionUsing = true;
                break;
            }
            case SESSIONS: {
                $metric->setExpression("ga:sessions");
                $metric->setAlias("sessions");
                break;
            }
        }

        // Создание объекта ReportRequest.
        $request = new Google_Service_AnalyticsReporting_ReportRequest();
        $request->setViewId($this->viewId);
        $request->setDateRanges($dateRange);
        if ($dimensionUsing)
            $request->setDimensions(array($dimension));
        $request->setMetrics(array($metric));

        $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
        $body->setReportRequests(array($request));
        return $analytics->reports->batchGet($body);
    }

    public function printResults($reports)
    {
        for ($reportIndex = 0; $reportIndex < count($reports); $reportIndex++) {
            $report = $reports[$reportIndex];
            $header = $report->getColumnHeader();
            $dimensionHeaders = $header->getDimensions();
            $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
            $rows = $report->getData()->getRows();

            for ($rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
                $row = $rows[$rowIndex];
                $dimensions = $row->getDimensions();
                $metrics = $row->getMetrics();
                for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
                    print($dimensionHeaders[$i] . ": " . $dimensions[$i] . "\n");
                }

                for ($j = 0; $j < count($metricHeaders) && $j < count($metrics); $j++) {
                    $entry = $metricHeaders[$j];
                    $values = $metrics[$j];
                    print("Metric type: " . $entry->getType() . "\n");
                    for ($valueIndex = 0; $valueIndex < count($values->getValues()); $valueIndex++) {
                        $value = $values->getValues()[$valueIndex];
                        print($entry->getName() . ": " . $value . "\n");
                    }
                }
            }
        }
    }

    public function prepareResultToSlackNotification($results, $applicationKey)
    {
        $normalizeResult = array();
        $color = ($applicationKey == COZMOBET_KEY) ? '#0000ff' : '#008000';

        foreach ($results as $queryKey => $reports) {

            $fields = array();

            for ($reportIndex = 0; $reportIndex < count($reports); $reportIndex++) {
                $report = $reports[$reportIndex];
                $header = $report->getColumnHeader();
                $dimensionHeaders = $header->getDimensions();
                $metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
                $rows = $report->getData()->getRows();

                for ($rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
                    $row = $rows[$rowIndex];
                    $dimensions = $row->getDimensions();
                    $metrics = $row->getMetrics();

                    $entry = $metricHeaders[0];
                    $values = $metrics[0];
                    $value = $values->getValues()[0];

                    if (isset($dimensionHeaders) && (isset($dimensions) && (count($dimensionHeaders) > 0) && (count($dimensions) > 0)))
                        $fields[] = [
                            'title' => $dimensions[0],
                            'value' => $entry->getName() . ": " . $value,
                            'short' => false
                        ];
                    else
                        $fields[] = [
                            'value' => $value,
                            'short' => false
                        ];
                }
            }

            $normalizeResult[] = [
                'fallback' => 'google analytics',
                'pretext' => $this->getPretext($queryKey),
                'color' => $color,
                'fields' => $fields
            ];
        }
        return $normalizeResult;
    }

    private function getPretext($commandKey)
    {
        switch ($commandKey) {
            case USERS: {
                $pretext = 'Пользователи';
                break;
            }
            case PAGES: {
                $pretext = 'Просмотры страниц';
                break;
            }
            case USERS_COUNTRIES: {
                $pretext = 'Пользователи по странам';
                break;
            }
            case TOP_PAGES: {
                $pretext = 'Самые популярные страницы';
                break;
            }
            case SESSIONS_TRAFFIC_REFERRAL: {
                $pretext = 'Переходы';
                break;
            }
            case BOUNCE_RATE: {
                $pretext = 'Процент отказов';
                break;
            }
            case USERS_OS: {
                $pretext = 'Операционные системы';
                break;
            }
            case SESSIONS: {
                $pretext = 'Сеансы';
                break;
            }
            default:
            {
                $pretext = 'Не задано';
                break;
            }
        }

        return $pretext;
    }


}