<?php

namespace SyncQcloudCos;

use Composer\Script\Event;

class Composer
{
    public static function removeUnusedModels(Event $event)
    {
        $composer = $event->getComposer();
        $extra = $composer->getPackage()->getExtra();
        $listedModels = $extra['tencentcloud/monitor'] ?? [];

        if ($listedModels) {
            $vendorPath = $composer->getConfig()->get('vendor-dir');
            $dir = "{$vendorPath}/tencentcloud/monitor/src/TencentCloud/Monitor/V20180724/Models";
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (!in_array($file, $listedModels)) {
                    unlink("{$dir}/{$file}");
                }
            }

            $generator = $composer->getAutoloadGenerator();
            $generator->dump($composer->getConfig(), $composer->getRepositoryManager()->getLocalRepository(), $composer->getPackage(), $composer->getInstallationManager(), 'composer', true);
        }
    }
}
