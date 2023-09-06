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
        $client->setCosConfig('schema', 'https');
        $result = $client->describeDocProcessBuckets(['Bucket' => $bucket]);

        return $result['TotalCount'] === '1';
    }

    /**
     * 判断是否支持文件预览
     *
     * @param string $fileUrl
     * @return bool
     */
    public static function isFileExtensionSupported($fileUrl)
    {
        $extension = pathinfo($fileUrl, PATHINFO_EXTENSION);
        if (empty($extension)) {
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