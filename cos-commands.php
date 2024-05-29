<?php

if (!class_exists('WP_CLI')) {
    return;
}

class COS_CLI_Commands
{

    /**
     * 同步文件夹到 COS
     *
     * ## OPTIONS
     *
     * <path>
     * : 要同步的文件夹
     *
     * ## EXAMPLES
     *
     *     wp cos upload wp-content/uploads
     *
     * @when after_wp_load
     */
    public function upload($args, $assoc_args)
    {
        list($path) = $args;
        $dir = ABSPATH . $path;
        if (!is_dir($dir)) {
            WP_CLI::error("Directory not found: $dir");
        }

        WP_CLI::line("Uploading files from $dir to COS...");

        $files = cos_read_dir_queue(ABSPATH, $path);
        if (empty($files)) {
            WP_CLI::success("No files to upload.");
            return;
        }

        foreach ($files as $file) {
            $status = cos_file_upload($file['key'], $file['filepath']);
            if ($status) {
                WP_CLI::line("Uploaded: {$file['key']}");
            } else {
                WP_CLI::line("Failed: {$file['key']}");
            }
        }

        $total = count($files);
        WP_CLI::success("Uploaded $total files.");
    }
}

WP_CLI::add_command('cos', 'COS_CLI_Commands', ['shortdesc' => 'Commands used to operate COS.']);
