<?php

/*
 * Yate core API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace YateAPI;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Test USSD application. Echo-like with session context storage.
 */
class USSDTestApp extends USSD {

    function __construct($uri, $logname = null) {
        if (!is_null($logname)) {
            $logger = new Logger('USSD_Test');
            $logger->pushHandler(new StreamHandler($logname, Logger::DEBUG));
        } else {
            $logger = null;
        }
        parent::__construct($uri, $logger);
    }

    function Run() {
        $r = parent::processCallback();
        $this->logDebug("Callback parsed, result" . $r);
        switch ($this->sessionState) {
            case 'start':
                $this->logDebug("Start from gateway", $this->vars);
            case 'progress':
                $this->makeAnswer();
                break;
            case 'stop':
                $this->logDebug("Stopped from gateway", $this->vars);
        }
    }

    protected function makeAnswer() {
        switch ('text' . $this->vars['text']) {
            case 'text0':
                $text = "Cancelled from network with message";
                $this->sessionStop($text);
                break;
            case 'text00':
                $text = null;
                $this->sessionStop($text);
                break;
            case 'text000':
                $this->sessionStopOnError();
                break;
            default:
                $this->sessionVars['history'] = isset($this->sessionVars['history']) //
                        ? $this->sessionVars['history'] . ',' . $this->vars['text'] //
                        : $this->vars['text'];
                $text = "USSD test: enter something to echo, 0 or 00 for cancel from network or just cancel the session\n\n"
                        . "History: " . $this->sessionVars['history'];
                $this->sessionAnswer($text);
        }
    }

}
