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
            $keepVersion = 'V20180724';
            $monitorPath = "{$vendorPath}/tencentcloud/monitor/src/TencentCloud/Monitor";

            $items = scandir($monitorPath);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item === $keepVersion) {
                    continue;
                }

                $path = "{$monitorPath}/{$item}";
                if (is_dir($path)) {
                    self::deleteDir($path);
                }
            }

            $v20180724Path = "{$monitorPath}/{$keepVersion}/Models";
            $files = scandir($v20180724Path);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                if (!in_array($file, $listedModels)) {
                    unlink("{$v20180724Path}/{$file}");
                }
            }

            $generator = $composer->getAutoloadGenerator();
            $generator->dump($composer->getConfig(), $composer->getRepositoryManager()->getLocalRepository(), $composer->getPackage(), $composer->getInstallationManager(), 'composer', true);
        }
    }

    protected static function deleteDir(string $dir): void
    {
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($ri as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($dir);
    }
}
