<?php

/*
 * Yate core API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace YateAPI;

/**
 * USSD menu engine. Extends USSD API to provide application engine with 
 * different dialogues managed by different classes.
 * Wiki: https://github.com/pavlyuts/yate-api/wiki/USSD-Menu-Engine
 */
class USSDMenuEngine extends USSD {

    protected $routeMap;
    protected $devMode;

    /**
     * @param array $routeMap - $routeMap is an associateve array of USSD codes 
     *        to route (key) and classes to invoke for handling (string). 
     *        Engine use it in a case of a new session detected or 'handler' 
     *        session variable not found. 'default' keyword used in a case of 
     *        no match. Codes mean digits between the first '*' and next non-digit 
     *        symbol ('*' or '#')
     *        Class must me exteded from USSDMenuHandler class.
     * $routeMap = [
     *     'default' => '\MySpace\DefaultHandler',
     *     '100' => '\MySpace\Info', // for '*100#', '*100*[whatever]#', e.t.c.
     *     '101' => '\MySpace\Voucher',
     *   ];
     * 
     * @param string $uri - URI of USSD gateway interface to call
     * @param \Psr\Log\LoggerInterface $logger - logger to log, loglevel depends 
     *        of logger parameters.
     */
    public function __construct($routeMap, $uri, \Psr\Log\LoggerInterface $logger = null, $devMode = false) {
        $this->routeMap = $routeMap;
        $this->devMode = $devMode;
        parent::__construct($uri, $logger);
    }

    /**
     *  Call it to process HTTP request from USSD gateway, it will do all the job.
     * 
     * @return boolean true on success and false on error
     */
    public function processCallback() {
        if (!parent::processCallback()) {
            return false;
        }
        $this->logDebug("Starting to process USSD", $this->vars);
        switch ($this->sessionState) {
            case 'start':
                ($this->devMode) ? $this->devMode() : null;
                $handlerClass = $this->getRoute();
                $this->startNewSession($handlerClass);
                break;
            case 'progress':
                $this->continueDialogue();
                break;
            case 'stop':
                $this->logDebug("Session stopped from gateway", $this->vars);
        }
    }

    /**
     * Call this to start menu from network. Do not use to notify, use 'notify' 
     * method instead.
     * 
     * @param string $msisdn - phone number to start USSD session with
     * @param string $handlerClass - dialogue handler class. Mind method
     *        processDialogueStartFromNetwork will be called. Override or 
     *        processDialogueStart method be used instead.
     * @return boolean true for success
     */
    public function startMenuFromNetwork($msisdn, $handlerClass = null) {
        $vars = [
            'msisdn' => $msisdn,
        ];
        $handlerClass = $handlerClass ?? ($this->routeMap['default'] ?? '\YateAPI\USSDMenuHandler');
        try {
            $handler = new $handlerClass($this->logger);
        } catch (\Error $e) {
            $this->logError("Unknown handler class '$handlerClass' to start dialogue from network");
            $this->sessionStopOnError();
            return false;
        }
        $next = $handler->StartDialogueFromNetwork($vars);
        if ($next['command'] !== 'continue') {
            $this->logError("Handler '$handlerClass' returned " . $next['command'] . " trying to start dialogue from network");
            return false;
        }
        return $this->sessionStart($msisdn, $next['message'], $next['sessionvars']);
    }

    protected function getRoute() {
        if ((1 !== preg_match('/^\*(?<code>[0-9]+)/', $this->vars['text'], $matches)) && (!isset($matches['code']))) {
            $this->sessionStopOnError();
            exit;
        }
        return $this->routeMap[$matches['code']] ?? ($this->routeMap['default'] ?? '\YateAPI\USSDMenuHandler');
    }

    protected function startNewSession($handlerClass) {
        $this->logDebug("starting new session with handler '$handlerClass'");
        try {
            $handler = new $handlerClass($this->logger);
        } catch (\Error $e) {
            $this->logError("Unknown handler class to route '$handlerClass'");
            $this->sessionStopOnError();
            return false;
        }
        return $this->processAnswer($handler->startDialogue($this->vars));
    }

    protected function continueDialogue() {
        if (!isset($this->sessionVars['handler'])) {
            $this->logError("Handler is not set to continue session, terminating",
                    ['vars' => $this->vars, 'sessionvars' => $this->sessionVars]);
            $this->sessionStopOnError();
            return false;
        }
        $this->logDebug("Continue session with handler '" . $this->sessionVars['handler'] . "'");
        try {
            $handler = new $this->sessionVars['handler']($this->logger);
        } catch (\Error $e) {
            $this->logError("Unknown handler class to continue '" . $this->sessionVars['handler'] . "'");
            $this->sessionStopOnError();
            return false;
        }
        return $this->processAnswer($handler->continueDialogue($this->vars, $this->sessionVars));
    }

    protected function processAnswer($params) {
        $this->logDebug("processing answer from handler", $params);
        switch ($params['command']) {
            case 'continue':
                $this->setSessionVars($params['sessionvars'] ?? [] );
                return $this->sessionAnswer($params['message']);
            case 'stop':
                return $this->sessionStop($params['message']);
            case 'jump':
                return $this->doJump($params);
        }
    }

    protected function doJump($params) {
        $this->logDebug("Jump to handler '" . $params['jump'] . "'");
        try {
            $newHandler = new $params['jump']($this->logger);
        } catch (\Error $e) {
            $this->logError("Unknown handler class to jump '" . $params['jump'] . "'");
            $this->sessionStopOnError();
            return false;
        }
        return $this->processAnswer($newHandler->jumpHere($this->vars, $params['sessionvars']));
    }

    protected function devMode() {
        if ((1 == preg_match('/^\*[0-9]+(.+)$/', $this->vars['text'], $matches)) && ($matches[1] != '#')) {
            $this->vars['text'] = $matches[1];
            $this->logDebug("Dev mode, tweak text to '" . $matches[1] . "'");
        } else {
            $this->logError("Wrong 'text' in dev mode: '" . $this->vars['text'] . "'");
        }
    }

}
