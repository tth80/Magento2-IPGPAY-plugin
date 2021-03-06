<?php
/**
 * @copyright Copyright (c) 2017 IPG Group Limited
 * All rights reserved.
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE.txt file for details.
 **/
namespace IPGPAY\IPGPAYMagento2\API\Request;

use IPGPAY\IPGPAYMagento2\API\Config;
use IPGPAY\IPGPAYMagento2\API\Exceptions;
use IPGPAY\IPGPAYMagento2\API\Functions;
use IPGPAY\IPGPAYMagento2\API\Response\ResponseAbstract;

/**
 * Class RequestAbstract
 * @package IPGPAY\Request
 */
abstract class RequestAbstract
{
    /**
     * @var string
     */
    protected $APIBaseUrl;
    /**
     * @var integer
     */
    protected $APIClientId;
    /**
     * @var string
     */
    protected $APIKey;
    /**
     * @var integer
     */
    protected $TestMode = 0;
    /**
     * @var integer
     */
    protected $Notify = 1;
    /**
     * @var array
     */
    protected $RequestParams = [];

    /**
     * Build the request params
     * To be implemented by the extended classes
     *
     * @return mixed
     */
    abstract protected function buildRequestParams();

    /**
     * Get the IPGPAY for the request
     *
     * @return mixed
     */
    abstract protected function getRequestUrl();

    /**
     * @param array $config
     */
    function __construct(array $config = [])
    {
        if (empty($config)) {
            $this->APIBaseUrl = Config::API_BASE_URL;
            $this->APIClientId = Config::API_CLIENT_ID;
            $this->APIKey = Config::API_KEY;
            $this->Notify = Config::NOTIFY;
            $this->TestMode = Config::TEST_MODE;
        } else {
            $this->APIBaseUrl = $config['api_base_url'];
            $this->APIClientId = $config['api_client_id'];
            $this->APIKey = $config['api_key'];
            $this->Notify = $config['notify'];
            $this->TestMode = $config['test_mode'];
        }
    }


    /**
     * Validate the request parameters
     *
     * @throws Exceptions\InvalidRequestException
     */
    protected function validate()
    {
        if (empty($this->APIBaseUrl)) {
            throw new Exceptions\InvalidRequestException("API URL is missing.");
        } elseif (filter_var($this->APIBaseUrl, FILTER_VALIDATE_URL) === false) {
            throw new Exceptions\InvalidRequestException("API URL is invalid.");
        }

        if (empty($this->APIClientId)) {
            throw new Exceptions\InvalidRequestException("API Client Id is missing.");
        } else {
            if (!Functions::isValidSqlInt($this->APIClientId)) {
                throw new Exceptions\InvalidRequestException("Invalid API Client Id");
            }
        }

        if (empty($this->APIKey)) {
            throw new Exceptions\InvalidRequestException("API Key is missing.");
        }
    }

    /**
     * Validate, build request params and send to IPGPAY
     * Return a response
     *
     * @return ResponseAbstract
     * @throws Exceptions\CommunicationException
     */
    public function sendRequest()
    {
        $this->validate();
        $this->buildRequestParams();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getRequestUrl());
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->RequestParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $msg = "cURL error: ".curl_errno($ch)." ".curl_error($ch);
            curl_close($ch);
            throw new Exceptions\CommunicationException($msg);
        }

        curl_close($ch);
        return ResponseAbstract::factory($response);
    }
}
