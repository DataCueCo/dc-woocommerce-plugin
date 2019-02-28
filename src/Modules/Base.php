<?php

namespace DataCue\WooCommerce\Modules;

use DataCue\WooCommerce\Utils\Log;

/**
 * Class Base
 * @package DataCue\WooCommerce\Modules
 */
abstract class Base
{
    /**
     * @var \DataCue\Client
     */
    protected $client;

    /**
     * @var Log
     */
    private $logger = null;

    /**
     * Generate Module Object
     * @param $client
     * @param array $options
     * @return Base
     */
    public static function registerHooks($client, $options = [])
    {
        return new static($client, $options);
    }

    /**
     * Base constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, $options = [])
    {
        $this->client = $client;

        if (array_key_exists('debug', $options) && $options['debug']) {
            $this->logger = new Log();
        }
    }

    /**
     * Log function
     * @param $message
     */
    protected function log($message)
    {
        if (!is_null($this->logger)) {
            $this->logger->info($message);
        }
    }
}
