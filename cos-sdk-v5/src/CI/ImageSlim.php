<?php

namespace SyncQcloudCos\CI;

class ImageSlim
{
    public static function checkStatus($client, $bucket)
    {
        $result = $client->getImageSlim(['Bucket' => $bucket]);
        return $result['Status'];
    }

    public static function open($client, $bucket, $mode, $suffix)
    {
        return $client->openImageSlim(
            ['Bucket' => $bucket, 'SlimMode' => $mode, 'Suffixs' => ['Suffix' => explode(',', $suffix)]]
        );
    }

    public static function close($client, $bucket)
    {
        return $client->closeImageSlim(['Bucket' => $bucket]);
    }
}
