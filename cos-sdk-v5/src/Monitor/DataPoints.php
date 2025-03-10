<?php

namespace SyncQcloudCos\Monitor;

use DateTime;
use DateTimeZone;
use TencentCloud\Common\Credential;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Monitor\V20180724\Models\GetMonitorDataRequest;
use TencentCloud\Monitor\V20180724\MonitorClient;

class DataPoints
{
    const NAMESPACE_COS = 'QCE/COS';
    const NAMESPACE_CI = 'QCE/CI';

    const PERIOD_60 = 60;
    const PERIOD_300 = 300;
    const PERIOD_3600 = 3600;
    const PERIOD_86400 = 86400;

    const METRIC_READ = 'StdReadRequests'; // 标准存储读请求
    const METRIC_WRITE = 'StdWriteRequests'; // 标准存储写请求

    const METRIC_STORAGE = 'StdStorage'; // 标准存储-存储空间
    const METRIC_OBJECT_NUMBER = 'StdObjectNumber'; // 标准存储-对象数量

    const METRIC_ACCELER_TRAFFIC_DOWN = 'AccelerTrafficDown'; // 全球加速下行流量
    const METRIC_INTERNET_TRAFFIC = 'InternetTraffic'; // 外网下行流量
    const METRIC_INTERNAL_TRAFFIC = 'InternalTraffic'; // 内网下行流量
    const METRIC_CDN_ORIGIN_TRAFFIC = 'CdnOriginTraffic'; // CDN 回源流量

    const METRIC_IMAGE_BASICS_REQUESTS = 'ImageBasicsRequests'; // 图片基础处理请求次数

    const METRIC_DOCUMENT_HTML_REQUESTS = 'DocumentHtmlRequests'; // 文档转HTML请求数
    const METRIC_DOCUMENT_HTML_SUCCESS_REQUESTS = 'DocumentHtmlSuccessRequests'; // 文档转HTML请求成功数
    const METRIC_DOCUMENT_HTML_FAIL_REQUESTS = 'DocumentHtmlFailRequests'; // 文档转HTML请求失败数

    const METRIC_CI_CDN_ORIGIN_TRAFFIC = 'CdnOriginTraffic'; // CDN回源流量：CI数据从存储桶传输到腾讯云CDN边缘节点产生的流量
    const METRIC_CI_INTERNET_TRAFFIC_UP = 'InternetTrafficUp'; // 外网出流量：CI数据通过互联网从存储桶下载到客户端产生的流量

    const METRIC_TEXT_AUDITING_TASKS = 'TextAuditingTasks'; // 文本审核任务数
    const METRIC_TEXT_AUDITING_SUCCESS_TASKS = 'TextAuditingSuccessTasks'; // 文本审核任务成功数
    const METRIC_TEXT_AUDITING_FAIL_TASKS = 'TextAuditingFailTasks'; // 文本审核任务失败数

    /**
     * @var string $bucket
     */
    private $bucket;

    /**
     * @var array $options
     */
    private $options;

    /**
     * @var string $start
     */
    private $start;

    /**
     * @var string $end
     */
    private $end;

    /**
     * @var MonitorClient $client
     */
    private $client;

    public function __construct($bucket, $options = [], $start = null, $end = null)
    {
        $this->bucket = $bucket;
        $this->options = $options;

        if (empty($start) || empty($end)) {
            list($start, $end) = $this->genStartEndTime();
        }
        $this->start = $start;
        $this->end = $end;

        $cred = new Credential($options['secret_id'], $options['secret_key']);
        $this->client = new MonitorClient($cred, 'ap-guangzhou');
    }

    protected function getTimezone()
    {
        return new DateTimeZone(get_option('timezone_string') ?: date_default_timezone_get());
    }

    protected function formatTime($date)
    {
        $timezone = $this->getTimezone();
        foreach ($date as $key => $value) {
            $date[$key] = (new DateTime('@' . $value))->setTimezone($timezone)->format('Y-m-d');
        }
        return $date;
    }

    protected function genStartEndTime()
    {
        $timezone = $this->getTimezone();

        $start = (new DateTime('-30 days', $timezone))->format(DateTime::RFC3339);
        $end = (new DateTime('now', $timezone))->format(DateTime::RFC3339);

        return [$start, $end];
    }

    /**
     * @param string $metric
     * @param string $namespace
     * @param string $start
     * @param string $end
     * @return array
     */
    public function buildParams($metric = self::METRIC_READ, $namespace = self::NAMESPACE_COS, $start = null, $end = null)
    {
        $start = $start ?: $this->start;
        $end = $end ?: $this->end;

        return [
            'Namespace' => $namespace,
            'MetricName' => $metric,
            'Period' => self::PERIOD_86400,
            'StartTime' => $start,
            'EndTime' => $end,
            'Instances' => [
                [
                    'Dimensions' => [
                        [
                            'Name' => 'appid',
                            'Value' => $this->options['app_id']
                        ],
                        [
                            'Name' => 'bucket',
                            'Value' => $this->bucket
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getRequests()
    {
        $params = $this->buildParams(self::METRIC_READ);
        $read = $this->request($params);

        $date = $this->formatTime($read->Timestamps ?? []);

        $params = $this->buildParams(self::METRIC_WRITE);
        $write = $this->request($params);

        return [
            'date' => $date,
            'read' => $read->Values ?? [],
            'write' => $write->Values ?? [],
        ];
    }

    public function getDocumentHtmlRequests()
    {
        $params = $this->buildParams(self::METRIC_DOCUMENT_HTML_REQUESTS, self::NAMESPACE_CI);
        $requests = $this->request($params);

        $date = $this->formatTime($requests->Timestamps ?? []);

        $params = $this->buildParams(self::METRIC_DOCUMENT_HTML_SUCCESS_REQUESTS, self::NAMESPACE_CI);
        $success = $this->request($params);

        $params = $this->buildParams(self::METRIC_DOCUMENT_HTML_FAIL_REQUESTS, self::NAMESPACE_CI);
        $fail = $this->request($params);

        return [
            'date' => $date,
            'requests' => $requests->Values ?? [],
            'success' => $success->Values ?? [],
            'fail' => $fail->Values ?? [],
        ];
    }

    public function getTextAuditing()
    {
        $params = $this->buildParams(self::METRIC_TEXT_AUDITING_TASKS, self::NAMESPACE_CI);
        $requests = $this->request($params);

        $date = $this->formatTime($requests->Timestamps ?? []);

        $params = $this->buildParams(self::METRIC_TEXT_AUDITING_SUCCESS_TASKS, self::NAMESPACE_CI);
        $success = $this->request($params);

        $params = $this->buildParams(self::METRIC_TEXT_AUDITING_FAIL_TASKS, self::NAMESPACE_CI);
        $fail = $this->request($params);

        return [
            'date' => $date,
            'requests' => $requests->Values ?? [],
            'success' => $success->Values ?? [],
            'fail' => $fail->Values ?? [],
        ];
    }

    public function getStorage()
    {
        $params = $this->buildParams(self::METRIC_STORAGE);
        $storage = $this->request($params);

        $date = $this->formatTime($storage->Timestamps ?? []);

        return [
            'date' => $date,
            'storage' => $storage->Values ?? [],
        ];
    }

    public function getImageBasicsRequests()
    {
        $params = $this->buildParams(self::METRIC_IMAGE_BASICS_REQUESTS, self::NAMESPACE_CI);
        $request = $this->request($params);

        $date = $this->formatTime($request->Timestamps ?? []);

        return [
            'date' => $date,
            'request' => $request->Values ?? [],
        ];
    }

    public function getObjectNumber()
    {
        $params = $this->buildParams(self::METRIC_OBJECT_NUMBER);
        $objectNumber = $this->request($params);

        $date = $this->formatTime($objectNumber->Timestamps ?? []);

        return [
            'date' => $date,
            'objectNumber' => $objectNumber->Values ?? [],
        ];
    }

    public function getTraffic()
    {
        $params = $this->buildParams(self::METRIC_INTERNET_TRAFFIC);
        $internet = $this->request($params);

        $date = $this->formatTime($internet->Timestamps ?? []);

        $params = $this->buildParams(self::METRIC_INTERNAL_TRAFFIC);
        $internal = $this->request($params);

        $params = $this->buildParams(self::METRIC_CDN_ORIGIN_TRAFFIC);
        $cdn = $this->request($params);

        $params = $this->buildParams(self::METRIC_ACCELER_TRAFFIC_DOWN);
        $accelerate = $this->request($params);

        return [
            'date' => $date,
            'internet' => array_map([$this, 'bytes2MB'], $internet->Values ?? []),
            'internal' => array_map([$this, 'bytes2MB'], $internal->Values ?? []),
            'cdn' => array_map([$this, 'bytes2MB'], $cdn->Values ?? []),
            'accelerate' => array_map([$this, 'bytes2MB'], $accelerate->Values ?? []),
        ];
    }

    public function getCITraffic()
    {
        $params = $this->buildParams(self::METRIC_CI_CDN_ORIGIN_TRAFFIC, self::NAMESPACE_CI);
        $cdn = $this->request($params);

        $date = $this->formatTime($cdn->Timestamps ?? []);

        $params = $this->buildParams(self::METRIC_CI_INTERNET_TRAFFIC_UP, self::NAMESPACE_CI);
        $internet = $this->request($params);

        return [
            'date' => $date,
            'cdn' => array_map([$this, 'bytes2MB'], $cdn->Values ?? []),
            'internet' => array_map([$this, 'bytes2MB'], $internet->Values ?? []),
        ];
    }

    /**
     * 将字节数转换为兆字节(MB)。
     * 和腾讯云计算方式保持一致：用1000计算而不是1024。
     *
     * @param int $bytes
     * @return float
     */
    private function bytes2MB($bytes)
    {
        return round($bytes / 1000 / 1000, 2);
    }

    /**
     * @param array $params
     * @return object
     */
    public function request($params)
    {
        try {
            $request = new GetMonitorDataRequest();
            $request->fromJsonString(wp_json_encode($params));

            $response = $this->client->GetMonitorData($request);

            return $response->getDataPoints()[0] ?? (object)[];
        } catch (TencentCloudSDKException $e) {
            return (object)[];
        }
    }
}
