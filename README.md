# Yate mobile core API wrapper for PHP

## Purpose

This package intended to simplify communication to Yate core products from PHP application. Build for composer with PSR-4 autoload, uses PSR-3 object for logging.

*Not properly tested, use at your own risk!*

Classes available:
- **[Yate JSON API](https://github.com/pavlyuts/yate-api/wiki/Yate-JSON-API)** class wrapping general JSON API to most Yate comonent - both configuration and control.
- **[USSD gateway API](https://github.com/pavlyuts/yate-api/wiki/USSD-API-class)** class for USSD gateway interface for creating USSD applications
- **[USSD demo/test application](https://github.com/pavlyuts/yate-api/wiki/USSD-Demo-Test-Application)**, a good for learning and test USSD gateway interaction.
- **[USSD Menu/dialogue engine](https://github.com/pavlyuts/yate-api/wiki/USSD-Menu-Engine)**, for easy create USSD menus, dialogues and applications.
- **[USSD Menu handler](https://github.com/pavlyuts/yate-api/wiki/USSD-Menu-Handler)**, base class for menu handlers enriched with business logic.
- **[USSD Demo Menu handler](https://github.com/pavlyuts/yate-api/wiki/USSD-Demo-Menu-Handler)**, demo class, implementing simple USSD menus with examples of session vars management.
 

Please, refer [project Wiki](https://github.com/pavlyuts/yate-api/wiki) for details and usage example.

## Installation
In the Composer storage. Just add proper require section:

    "require": {
        "pavlyuts/yate-api": "*"
    }
It is a good idea to fix the version you use. Don't use next version without review, I can't promose backward compatibility even will try to keep it. Please, review the [changelog](https://github.com/pavlyuts/yate-api/blob/master/CHANGELOG.md) before to change used version.

## Dependencies
- psr/log: ^1.1
- rmccue/requests: ^1.7

## Yate documentation
Please, refer to [Yate core network documentation](https://yatebts.com/documentation/core-network-documentation/):
- [General JSON API](https://yatebts.com/documentation/core-network-documentation/json-api/)
- [USSD GW application API](https://yatebts.com/documentation/core-network-documentation/yateusgw-documentation/rest-api-for-ussd-gw/)
- [HSS subscriber management API](https://yatebts.com/documentation/core-network-documentation/yatehss-hss-hlr/json-api-subscriber-management/)
- [SMSC SMS sending API](https://yatebts.com/documentation/core-network-documentation/yatesmsc-documentation/yatesmsc-json-api-to-schedule-smss/)

Also, API for configuration and control of each core component is documented and may be used with this wrapper.
