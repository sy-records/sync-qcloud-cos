<?php

namespace SyncQcloudCos\CI;

use Qcloud\Cos\Exception\ServiceResponseException;

class Service
{
    public static function checkStatus($client, $bucket)
    {
        try {
            $result = $client->getCiService(['Bucket' => $bucket]);
            return $result['CIStatus'] === 'on';
        } catch (ServiceResponseException $e) {
            return false;
        }
    }
}