<?php

/*
 * Yate core API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace YateAPI;

use Requests;
use Psr\Log;

/**
 * USSD API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/yateusgw-documentation/rest-api-for-ussd-gw/
 */
class USSD extends Base {

    const PREFIX = 'USSDAPP';
    const SESSIONDATA = 'sessiondata';

    protected $uri;
    protected $sync;
    protected $sessionId = null;
    protected $sessionVars = null;
    protected $vars = null;
    protected $sessionState = null;

    function __construct($uri, Log\LoggerInterface $logger = null) {
        $this->uri = $uri;
        parent::__construct($logger);
    }

    /**
     * Process call from USSD GW
     * Call format: GET, {app uri}?id=${id}&msisdn=${msisdn}&text=${text}&operation=${operation}&sync=${sync}&seq=${seq}&code=${code}&user=${user}&hlr=${hlr}&scf=${scf}&menu_path=${menu_path}&sessiondata=${sessiondata}
     */
    function processCallback() {
        $this->logDebug("Start to process callback", ['GET' => $_GET]);
        return $this->checkRequiredParams($_GET) && $this->setSessionState($_GET) && $this->restoreSessionVars();
    }

    function sessionStart($msisdn, $message, array $sessionVars = null) {
        $params = [
            'operation' => 'ussr',
            'id' => uniqid(USSD::PREFIX),
            'msisdn' => $msisdn,
            'text' => $message,
        ];
        If (!is_null($sessionVars)) {
            $this->sessionVars = $sessionVars;
        }
        return $this->send($params);
    }

    function sessionAnswer($message) {
        if (!$this->inSession()) {
            $this->logError("Trying to answer without a session");
            return false;
        }
        $params = [
            'operation' => 'ussr',
            'id' => $this->sessionId,
            'text' => $message,
        ];
        return $this->send($params);
    }

    function sessionStop($text = null) {
        if (!$this->inSession()) {
            $this->logError("Trying to stop without a session");
            return false;
        }
        $params = [
            //pssr for MO originated, ussn for network-originated
            'operation' => (strpos($this->sessionId, USSD::PREFIX) === 0) ? 'ussn' : 'pssr',
            'id' => $this->sessionId,
            'text' => $text ?? 'USSD session finished',
        ];
        return $this->send($params);
    }

    function notify($msisdn, $message) {
        $params = [
            'operation' => 'ussn',
            'msisdn' => $msisdn,
            'text' => $message,
        ];
        return $this->send($params);
    }

    protected function send($params) {
        if ($this->sessionState == 'stop') {
            $this->logError("Trying to answer on stopped session", $this->vars);
            return false;
        }
        return $this->sync ? $this->sendSync($params) : $this->sendAsync($params);
    }

    protected function sendSync($params) {
        $this->logDebug("Trying to send sync", ['params' => $params]);
        if (!is_null($this->sessionVars)) {
            header("copyparams: " . USSD::SESSIONDATA);
            header(USSD::SESSIONDATA . ": " . urlencode(json_encode($this->sessionVars)));
        }
        header("operation: " . $params['operation']);
        echo $params['text'];
        return true;
    }

    protected function sendAsync($params) {
        $this->logDebug("Trying to send async", ['params' => $params]);
        if (!is_null($this->sessionVars)) {
            $params['copyparams'] = USSD::SESSIONDATA;
            $params[USSD::SESSIONDATA] = json_encode($this->sessionVars);
        }

        try {
            $response = Requests::post($this->uri, array(), $params);
        } catch (\Requests_Exception $e) {
            $this->logError('HTTP error ' . $e->getMessage(), $params);
            return false;
        }
        if (!$response->success) {
            $this->logError('HTTP error ' . $response->status_code, array('params' => $params, 'response' => $response));
            return false;
        }
        return true;
    }

    function inSession() {
        return ($this->sessionState == 'progress') || ($this->sessionState == 'start');
    }

    function getSessionVars() {
        return $this->sessionVars;
    }

    function getVars() {
        return $this->vars;
    }

    function getSessionState() {
        return $this->sessionState;
    }

    function setSessionVars(array $sessionVars) {
        $this->sessionVars = $sessionVars;
    }

    function addSessionVar($name, $value) {
        $this->sessionVars[$name] = $value;
    }

    function delSessionVar($name) {
        unset($this->sessionVars[$name]);
    }

    protected function sessionStopOnError() {
        if ($this->inSession()) {
            $this->logError("Session stopped on error, customer notified");
            return $this->sessionStop("USSD applicaton internal error.\n\nPlease, try again later");
        }
        $this->logError("No active session, nothing to stop");
        return false;
    }

    protected function checkRequiredParams(array $vars) {
        if (!isset($vars['id']) || !isset($vars['operation']) || !isset($vars['sync']) || !isset($vars['msisdn'])) {
            $this->logError("Required fields missed in callback", [$vars]);
            return false;
        }
        return true;
    }

    protected function setSessionState(array $vars) {
        switch ($vars['operation']) {
            case 'stop':
                $this->sessionState = 'stop';
                break;
            case 'pssr':
                $this->sessionState = 'start';
                break;
            case 'ussr':
                $this->sessionState = 'progress';
                break;
            default :
                $this->logError("Unknown or unsopported operation", [$vars]);
                return false;
        }
        $this->sessionId = $vars['id'];
        $this->sync = $vars['sync'] == 'true';
        $this->vars = $vars;
        $this->logDebug("Session state set");
        return true;
    }

    protected function restoreSessionVars() {
        if (isset($this->vars['sessiondata']) && ($this->vars['sessiondata'] != '')) {
            if (is_null($this->sessionVars = json_decode(urldecode($this->vars['sessiondata']), true))) {
                $this->logError("Error decoding sesson data JSON", array($this->vars));
                return false;
            }
            $this->logDebug("Session data extraced", $this->sessionVars);
            unset($this->vars['sessiondata']);
        } else {
            $this->logDebug("Session data not found, bypassing");
        }
        return true;
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
