<?php

/*
 * Yate core API wrapper
 * API docs: https://yatebts.com/documentation/core-network-documentation/
 * (c) Alexey Pavlyuts <alexey@pavlyuts.ru>
 */

namespace YateAPI;

use Requests;
use Psr\Log\LoggerInterface;

/**
 * General Yate JSON API wrapper
 */
class YateAPI extends Base {

    protected const JSON_FLAGS = 0;

    protected $config;
    protected $status = false;
    protected $message = null;
    protected $result = null;
    protected $resultRaw = null;

    /**
     * Need config array and PSR-3 compatible log object
     * 
     * Config array as follows:
     * $config = [
     *    '*' => [ //It is default to use if no record found for exact node name 
     *        'secret' => 'MobileAPIsecret',
     *        'uri' => 'http(s)://{ip or hostname}',
     *    ],
     *    'ucn' => [
     *        'secret' => 'MobileAPIsecret-ucn',
     *        'uri' => 'http(s)://{ucn ip or hostname}',
     *    ],
     *    'hss' => [
     *        'secret' => 'MobileAPIsecret-hss',
     *        'uri' => 'http(s)://{hss ip or hostname}',
     *    ],
     *    // e.t.c. for every component
     * ];
     * */
    public function __construct(array $config, LoggerInterface $logger = null) {
        $this->config = $config;
        parent::__construct($logger);
    }

    /**
     * 
     * @param string $request - what to request, refer to Yate docs
     * @param array $params - request params, omitted if null 
     * @param string $node - optional node name. Used to find correct endpoint in 
     * config and added to request even used defauls (*) config
     * 
     * @return true on success, false on failure
     */
    function call(string $request, array $params = null, array $node = null ) {
        if (false === ($conf = $this->getConfigByNode($node))) {
            return $this->setError("No config found for node '$node'");
        }
        try {
            $response = Requests::post($conf['uri'] . '/api.php',
                            ['Content-Type' => 'application/json', 'X-Authentication' => $conf['secret']],
                            $this->prepareJSON($request, $node, $params));
        } catch (\Requests_Exception $e) {
            $this->logError('HTTP error ' . $e->getMessage(), ['config' => $conf, 'json' => $this->prepareJSON($request, $node, $params)]);
            return $this->setError($e->getMessage());
        }
        if (!$response->success) {
            $this->logError('HTTP error ' . $response->status_code, ['config' => $conf, 'json' => $this->prepareJSON($request, $node, $params)]);
            return $this->setError('HTTP error ' . $response->status_code);
        }
        if (is_null($this->result = json_decode($this->resultRaw = $response->body, true))) {
            $this->logError("Can't decode JSON data", ['JSON' => $response->body]);
            return $this->setError("Can't decode JSON data");
        }
        return $this->processYateAnswer();
    }

    function ok() {
        return $this->status;
    }

    function resultRaw() {
        return $this->resultRaw;
    }

    function resultArray() {
        return $this->result;
    }

    function resultObject() {
        return is_null($this->resultRaw) ? null : json_decode($this->resultRaw);
    }

    function getMessage() {
        return $this->message;
    }

    protected function getConfigByNode($node) {
        $this->logDebug("Trying to find config for node", ['node' => $node, 'config' => $this->config]);
        if (is_null($node) || !isset($this->config[$node])) {
            if (isset($this->config['*'])) {
                $this->logDebug("Use default record for '*'");
                return $this->config['*'];
            } else {
                $this->logError("Config not found for node $node", $this->config);
                return false;
            }
        }
        $this->logDebug("Use found record for '$node'", $this->config[$node]);
        return $this->config[$node];
    }

    protected function prepareJSON($request, $node, $params) {
        $res['request'] = $request;
        if (!is_null($node)) {
            $res['node'] = $node;
        }
        if (!is_null($params)) {
            $res['params'] = $params;
        }
        return json_encode($res, YateAPI::JSON_FLAGS);
    }

    protected function processYateAnswer() {
        if (!isset($this->result['code'])) {
            $this->logError('No status code in the answer, failure', ['answer' => $this->resultRaw]);
            return $this->setError('No status code in the answer, failure');
        }
        If ($this->result['code'] != 0) {
            $this->logError('Yate error: ' . ($this->result['message'] ?? 'unknown error'), ['answer' => $this->resultRaw]);
            return $this->setError('Yate error: ' . ($this->result['message'] ?? 'unknown error'));
        }
        $this->status = true;
        $this->message = null;
        return true;
    }

    protected function setError($message) {
        $this->status = false;
        $this->message = $message;
        return false;
    }

}
