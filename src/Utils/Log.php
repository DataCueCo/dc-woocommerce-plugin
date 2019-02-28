<?php

namespace DataCue\WooCommerce\Utils;

/**
 * Class Log
 * @package DataCue\WooCommerce\Utils
 */
class Log
{
    /**
     * write logs
     * @param $message
     */
    public static function info($message)
    {
        if(!is_string($message)) {
            $message = json_encode($message);
        }
        $file = fopen(__DIR__ . "/../../logs.log","a");
        echo fwrite($file, "\n" . date('Y-m-d h:i:s') . " :: " . $message);
        fclose($file);
    }
}
