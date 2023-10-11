<?php

namespace SyncQcloudCos\Monitor;

class Charts
{
    static $colors;

    public static function setColors($value)
    {
        self::$colors = $value;
    }

    private static function generateChartScript($elementId, $title, $series, $xaxis, $yaxisUnit = '')
    {
        $seriesData = wp_json_encode($series);
        $xaxisData = wp_json_encode($xaxis);
        $colors = wp_json_encode(self::$colors);

        return <<<HTML
<div id="{$elementId}" class="cos-chart"></div>
<script>
    var options = {
        title: {
            text: '{$title}'
        },
        colors: {$colors},
        series: {$seriesData},
        chart: {
            height: 350,
            type: 'area',
            toolbar: false
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth'
        },
        xaxis: {
            type: 'datetime',
            categories: {$xaxisData},
            labels: {
                format : 'yyyy/MM/dd'
            }
        },
        yaxis: {
            labels: {
              formatter: function(value) {
                 return value + ' {$yaxisUnit}';
              }
            }
        },
        tooltip: {
            x: {
                format: 'yyyy/MM/dd'
            },
        },
    };

    var chart = new ApexCharts(document.querySelector("#{$elementId}"), options);
    chart.render();
</script>
HTML;
    }

    public static function requests($data)
    {
        $series = [
            ['name' => '读请求数', 'data' => $data['read']],
            ['name' => '写请求数', 'data' => $data['write']]
        ];

        return self::generateChartScript('cos-requests-chart', '请求数', $series, $data['date'], '次');
    }

    public static function storage($data)
    {
        $series = [
            ['name' => '存储用量', 'data' => $data['storage']]
        ];

        return self::generateChartScript('cos-storage-chart', '存储用量', $series, $data['date'], 'MB');
    }

    public static function objectNumber($data)
    {
        $series = [
            ['name' => '对象数量', 'data' => $data['objectNumber']]
        ];

        return self::generateChartScript('cos-objectNumber-chart', '对象数量', $series, $data['date'], '个');
    }

    public static function traffic($data)
    {
        $series = [
            ['name' => '外网下行流量', 'data' => $data['internet']],
            ['name' => '内网下行流量', 'data' => $data['internal']],
            ['name' => 'CDN 回源流量', 'data' => $data['cdn']]
        ];

        return self::generateChartScript('cos-traffic-chart', '流量', $series, $data['date'], 'MB');
    }

    public static function ciStyle($data)
    {
        $series = [
            ['name' => '请求数', 'data' => $data['request']]
        ];

        return self::generateChartScript('cos-ci-style-chart', '图片基础处理', $series, $data['date'], '次');
    }

    public static function ciDocumentHtml($data)
    {
        $series = [
            ['name' => '请求数', 'data' => $data['requests']],
            ['name' => '成功次数', 'data' => $data['success']],
            ['name' => '失败次数', 'data' => $data['fail']]
        ];

        return self::generateChartScript('cos-ci-document-html-chart', '文档预览', $series, $data['date'], '次');
    }

    public static function ciTextAuditing($data)
    {
        $series = [
            ['name' => '请求数', 'data' => $data['requests']],
            ['name' => '成功次数', 'data' => $data['success']],
            ['name' => '失败次数', 'data' => $data['fail']]
        ];

        return self::generateChartScript('cos-ci-text-auditing-chart', '文本审核', $series, $data['date'], '次');
    }

    public static function ciTraffic($data)
    {
        $series = [
            ['name' => 'CDN 回源流量', 'data' => $data['cdn']],
            ['name' => '外网出流量', 'data' => $data['internet']]
        ];

        return self::generateChartScript('cos-ci-traffic-chart', 'CI 流量', $series, $data['date'], 'MB');
    }
}
