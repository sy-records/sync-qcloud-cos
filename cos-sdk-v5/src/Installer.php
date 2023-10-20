<?php

namespace SyncQcloudCos;

use Composer\Script\Event;

class Installer
{
    public static function postAutoloadDump(Event $event)
    {
        $dir = __DIR__ . '/../vendor/tencentcloud/monitor/src/TencentCloud/Monitor/V20180724/Models';
        $files = scandir($dir);
        foreach ($files as $file) {
            if (!in_array($file, ['.', '..', 'GetMonitorDataRequest.php', 'Instance.php', 'Dimension.php', 'GetMonitorDataResponse.php', 'DataPoint.php'])) {
                $event->getIO()->write("remove useless file: <info>$file</info>");
                unlink($dir . '/' . $file);
            }
        }
    }
}
