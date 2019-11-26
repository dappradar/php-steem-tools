<?php
namespace SteemTools;

use Exception;
use Psr\Log\LoggerInterface;

class SteemServiceLayer
{
    /**
     * @var bool|mixed
     */
    private $debug = false;

    /**
     * @var string[]|string
     */
    private $webserviceUrl = 'https://api.steemit.com';

    /**
     * @var bool
     */
    private $throwException = false;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (array_key_exists('debug', $config)) {
            $this->debug = $config['debug'];
        }
        if (array_key_exists('webservice_url', $config)) {
            $this->webserviceUrl = $config['webservice_url'];
        }
        if (array_key_exists('throw_exception', $config)) {
            $this->throwException = $config['throw_exception'];
        }

        if (array_key_exists('logger', $config)) {
            if ($config['logger'] instanceof LoggerInterface) {
                $this->logger = $config['logger'];
            }
        }
    }

    /**
     * @param $method
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function call($method, $params = array()) {
        $request = $this->getRequest($method, $params);
        $response = $this->curl($request);
        if (is_null($response) || array_key_exists('error', $response)) {
            if ($this->throwException) {
                if (is_null($response)) {
                    throw new Exception($method);
                } else {
                    if ($this->debug) {
                        $this->debug(
                            "Error response",
                            [
                                'code' => $this->statusCode,
                                'response' => $response
                            ]
                        );
                    }
                    throw new Exception($response['error']['message']);
                }
            } else {
                if (is_null($response)) {
                    $this->debug(
                        "We got no response...",
                        [
                            'method' => $method,
                            'params' => $params
                        ]
                    );
                } else {
                    $this->debug(
                        "We got an error response..",
                        [
                            'method' => $method,
                            'params' => $params,
                            'response' => $response
                        ]
                    );
                }
                die();
            }
        }
        return $response['result'];
    }

    /**
     * @param $method
     * @param $params
     * @return false|string
     */
    public function getRequest($method, $params) {
        $request = [
            "jsonrpc" => "2.0",
            "method" => $method,
            "params" => $params,
            "id" => 1
        ];
        $request_json = json_encode($request);

        if ($this->debug) {
            $this->debug('Request', ['request' => $request]);
        }

        return $request_json;
    }

    /**
     * @param string $data
     * @return bool|string
     */
    public function curl($data) {
        if (!is_array($this->webserviceUrl)) {
            $urls = [$this->webserviceUrl];
        } else {
            $urls = $this->webserviceUrl;
        }

        foreach ($urls as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $result = curl_exec($ch);
            $this->statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result = json_decode($result, true);
            if (isset($result['error']) || is_null($result)) {

                continue;
            }
            break;
        }

        if ($this->debug) {
            $this->debug('Result', ['response' => $result]);
        }

        return $result;
    }

    /**
     * @param string $message
     * @param array $context
     */
    private function debug(string $message, array $context)
    {
        if (!$this->logger) {
            print "$message \n";
            foreach ($context as $key => $value) {
                var_dump($key, $value);
            }
        } else {
            $this->logger && $this->logger->debug($message, $context);
        }
    }
}