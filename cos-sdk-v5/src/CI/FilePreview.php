<?php

namespace SyncQcloudCos\CI;

class FilePreview
{
    /**
     * @param \Qcloud\Cos\Client $client
     * @param string $bucket
     * @return bool
     */
    public static function checkStatus($client, $bucket)
    {
        $client->setCosConfig('scheme', 'https');
        $result = $client->describeDocProcessBuckets(['Bucket' => $bucket]);

        if (empty($result['DocBucketList'])) {
            return false;
        }

        $buckets = array_column($result['DocBucketList'], 'Name');

        return in_array($bucket, $buckets);
    }

    /**
     * 判断是否支持文件预览
     *
     * @param string $fileUrl
     * @param string $urlPrefix
     * @return bool
     */
    public static function isFileExtensionSupported($fileUrl, $urlPrefix = '')
    {
        $extension = pathinfo($fileUrl, PATHINFO_EXTENSION);
        if (empty($extension)) {
            return false;
        }

        if (!empty($urlPrefix) && strpos($fileUrl, $urlPrefix) === false) {
            return false;
        }

        $supported = [
            'pptx', 'ppt', 'pot','potx', 'pps', 'ppsx', 'dps', 'dpt', 'pptm', 'potm', 'ppsm',
            'doc', 'dot', 'wps', 'wpt', 'docx', 'dotx', 'docm', 'dotm',
            'xls', 'xlt', 'et', 'ett', 'xlsx', 'xltx', 'csv', 'xlsb', 'xlsm', 'xltm', 'ets',
            'pdf', 'lrc', 'c', 'cpp', 'h', 'asm', 's', 'java', 'asp', 'bat', 'bas', 'prg', 'cmd', 'rtf', 'txt', 'log', 'xml', 'htm', 'html'
        ];
        return in_array($extension, $supported);
    }
}
