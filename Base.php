<?php

/*
 * Yate core API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace YateAPI;

use Psr\Log;

/**
 * Base class with logging
 */
class Base {

    protected $logger;

    function __construct(Log\LoggerInterface $logger = null) {
        $this->logger = is_null($logger) ? new Log\NullLogger() : $logger;
    }

    protected function logInfo(string $message, array $dump = null) {
        $this->logger->info($message);
        if (!is_null($dump)) {
            $this->logger->debug('', $dump);
        }
    }

    /**
     * Logs error
     * 
     * @param string $message - error message
     * @param array $dump - extra data array to put in the log with debug level
     */
    protected function logError(string $message, array $dump = null) {
        $this->logger->error($message);
        if (!is_null($dump)) {
            $this->logger->debug('', $dump);
        }
    }

    /**
     * Logs debug information
     * 
     * @param string $message - message to log
     * @param array $dump - extra data array to put in the log
     */
    protected function logDebug(string $message, array $dump = null) {
        $this->logger->debug($message, (is_null($dump)) ? array() : $dump );
    }

}
