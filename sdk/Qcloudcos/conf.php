<?php
namespace Qcloudcos;

class Conf {
    // Cos php sdk version number.
    const VERSION = 'v4.2.2';
    const API_COSAPI_END_POINT = 'https://region.file.myqcloud.com/files/v2/';

    public static $APPID;
    public static $SECRET_ID;
    public static $SECRET_KEY;

    public function __construct(){
        $cos_options = get_option('cos_options', true);
        self::$APPID = esc_attr($cos_options['app_id']);
        self::$SECRET_ID = esc_attr($cos_options['secret_id']);
        self::$SECRET_KEY = esc_attr($cos_options['secret_key']);
    }

    /**
     * Get the User-Agent string to send to COS server.
     */
    public static function getUserAgent() {
        return 'cos-php-sdk-' . self::VERSION;
    }
}
