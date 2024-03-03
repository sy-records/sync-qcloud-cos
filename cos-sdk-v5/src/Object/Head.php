<?php

namespace SyncQcloudCos\Object;

use Qcloud\Cos\Exception\ServiceResponseException;

class Head
{
    public static function getContentLength($client, $bucket, $key)
    {
        try {
            $result = $client->HeadObject(['Bucket' => $bucket, 'Key' => $key]);
            return $result['ContentLength'] ?? 0;
        } catch (ServiceResponseException $e) {
            return 0;
        }
    }
}
