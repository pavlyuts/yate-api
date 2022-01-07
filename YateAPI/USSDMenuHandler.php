<?php

/*
 * Yate core API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace YateAPI;

/**
 * Class to handle a separate menu/app under USSDMenuEngine all the menu handling 
 * classes must extend this one.
 * wiki: https://github.com/pavlyuts/yate-api/wiki/USSD-Menu-Handler
 * 
 * See USSDMenuDemoHandler for usage example and project 
 */
class USSDMenuHandler extends Base {

    protected $vars;
    protected $sessionVars;
    protected $next;

    const ENTRY_LABEL = 'entry';
    
    function __construct(\Psr\Log\LoggerInterface $logger = null) {
        parent::__construct($logger);
        $this->logDebug("Handler '".static::class."' invoked");
    }
    
    final function StartDialogueFromNetwork($vars) {
        $this->setVars($vars);
        $this->processDialogueStartFromNetwork();
        return $this->compoundReturnData();
    }

    final function startDialogue($vars) {
        $this->setVars($vars);
        $this->processDialogueStart();
        return $this->compoundReturnData();
    }

    final function continueDialogue($vars, $sesionVars) {
        $this->setVars($vars, $sesionVars);
        $entryPoint = 'processDialogue' . ($this->sessionVars[static::ENTRY_LABEL] ?? "Default");
        $this->$entryPoint();
        return $this->compoundReturnData();
    }

    final function jumpHere($vars, $sesionVars) {
        $this->setVars($vars, $sesionVars);
        $entryPoint = isset($this->sessionVars[static::ENTRY_LABEL]) //
                ? 'processDialogue' . $this->sessionVars[static::ENTRY_LABEL] //
                : 'processJumpHereDefault';
        $this->$entryPoint();
        return $this->compoundReturnData();
    }

    protected function processDialogueStartFromNetwork() {
        //Override this if you need something more
        $this->processDialogueStart();
    }

    protected function processDialogueStart() {
        //Override and put business logic here
    }

    protected function processDialogueDefault() {
        //Override and put business logic here
    }

    protected function processJumpHereDefault() {
        //Override and put business logic here
    }

    final protected function setVars($vars, $sessionVars = null) {
        $this->vars = $vars;
        $this->sessionVars = $sessionVars;
        $this->next = ['command' => 'stop'];
        $this->sessionVars['handler'] = static::class;
    }

    final protected function nextContinue($entry = null, $message = '', $newHandler = null) {
        $this->next['command'] = 'continue';
        if (!is_null($entry)) {
            $this->sessionVars[static::ENTRY_LABEL] = $entry;
        }
        if ($message != '') {
            $this->next['message'] = $message; 
        }
        if (!is_null($newHandler)) {
            $this->sessionVars['handler'] = $newHandler;
        }
    }

    final protected function nextStop($message = null) {
        $this->next['command'] = 'stop';
        $this->next['message'] = $message;
    }

    final protected function nextJump($target, $entry = null) {
        $this->next['command'] = 'jump';
        $this->next['jump'] = $target;
        if (!is_null($entry)) {
            $this->sessionVars[static::ENTRY_LABEL] = $entry;
        }
    }

    final protected function nextError($message = null) {
        $this->nextStop();
        $this->next['message'] = $message ?? "USSD application internal error. Please, try again later";
    }

    final protected function compoundReturnData() {
        $data = $this->next;
        if (!is_null($this->sessionVars)) {
            $data['sessionvars'] = $this->sessionVars;
        }
        return $data;
    }

    protected function getDefaultMessage() {
        //override to get default handler stopping any reqiest with the same messsage
        return 'USSD functon ' . $this->vars['text'] . " is not implemented";
    }

    public function __call($name, $arguments) {
        $this->logError("Method '$name' of class '" . static::class . "' is called but not implemented");
        $this->nextError();
    }

}
