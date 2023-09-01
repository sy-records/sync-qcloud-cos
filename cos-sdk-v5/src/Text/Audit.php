<?php

namespace SyncQcloudCos\Text;

class Audit
{
    public static function comment($client, $bucket, $comment, $bizType = '')
    {
        $result = $client->detectText([
                                          'Bucket' => $bucket,
                                          'Input' => [
                                              'Content' => base64_encode($comment)
                                          ],
                                          'Conf' => [
                                              'BizType' => $bizType,
                                          ]
                                      ]);

        $response = [];
        $response['state'] = $result['JobsDetail']['State'] === 'Success';
        $response['result'] = (int)$result['JobsDetail']['Result'];
        $response['message'] = $result['JobsDetail']['Message'] ?? '';

        if ($response['state'] && empty($response['message'])) {
            $label = $result['JobsDetail']['Label'];
            $response['message'] = $result['JobsDetail']['Section'][0]["{$label}Info"]['Keywords'] ?? '';
        }

        return $response;
    }
}
