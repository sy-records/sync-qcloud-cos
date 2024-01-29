<?php

namespace SyncQcloudCos\CI;

use Qcloud\Cos\Exception\ServiceResponseException;

class OriginProtect
{
    public static function checkStatus($client, $bucket)
    {
        try {
            $result = $client->GetOriginProtect(['Bucket' => $bucket]);
            return $result['OriginProtectStatus'] === 'on';
        } catch (ServiceResponseException $e) {
            return false;
        }
    }
}
