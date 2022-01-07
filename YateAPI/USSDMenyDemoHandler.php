<?php

/*
 * Yate core API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace YateAPI;

/**
 * Menu demo handler to be a good starting point for own applicaton and example 
 * of how manu engine works.
 * Wiki: https://github.com/pavlyuts/yate-api/wiki/USSD-Demo-Menu-Handler
 */
class USSDMenyDemoHandler extends USSDMenuHandler {

    protected function processDialogueStart() {
        return $this->processDialogueDefault();
    }
    
    protected function processJumpHereDefault() {
        return $this->processDialogueDefault();
    }

    protected function processDialogueDefault() {
        $this->nextContinue('MainMenuChoice', "Demo main menu\n\n"
                . "1. Manage session vars\n"
                . "2. Show MSISDN\n"
                . "3. End session with custom message\n"
                . "4. End session with predefined message\n\n"
                . "Can't wait for your choice!");
    }

    protected function processDialogueMainMenuChoice() {
        switch ($this->vars['text']) {
            case '1':
                return $this->processDialogueVarsMain();
            case '2':
                return $this->processDialogueShowMSISDN();
            case '3':
                return $this->nextStop("USSD dialogue stopped with message\n\n Bye!");
            case '4':
                return $this->nextStop();
            default :
                return $this->processDialogueDefault();
        }
    }

    protected function processDialogueShowMSISDN() {
        $this->nextContinue('ShowMSISDNChoice', "Your MSISDN is +" . $this->vars['msisdn'] . "\n\n"
                . "0. Return to main menu\n\n"
                . "Any other input to exit");
    }

    protected function processDialogueShowMSISDNChoice() {
        switch ($this->vars['text']) {
            case '0':
                return $this->processDialogueDefault();
            default :
                return $this->nextStop("Thank you for visiting.\n\nAt least you know your MSISDN is +" . $this->vars['msisdn']
                                . "\n\nBye!");
        }
    }

    protected function processDialogueVarsMain() {
        $varText = '';
        foreach ($this->sessionVars as $key => $val) {
            if (($key != 'handler') && ($key != static::ENTRY_LABEL)) {
                $varText .= "['" . $key . "']='" . $val . "'\n";
            }
        }
        $text = "Manage sesion vars\n\n"
                . (($varText != '') ? $varText : "No session vars defined\n")
                . "\nSend\n{name}={value} to add or edit\n{name} to delete\n'0' to return";
        $this->nextContinue('VarsMainChoice', $text);
    }

    protected function processDialogueVarsMainChoice() {
        if ($this->vars['text'] === '0') {
            return $this->processDialogueDefault();
        }
        $parts = explode('=', $this->vars['text']);
        $key = $parts[0];
        $val = $parts[1] ?? null;
        if (($key != 'handler') && ($key != static::ENTRY_LABEL)) {
            if (is_null($val)) {
                unset($this->sessionVars[$key]);
            } else {
                $this->sessionVars[$key] = $val;
            }
        }
        return $this->processDialogueVarsMain();
    }
    
}
