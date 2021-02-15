<?php

namespace Inensus\SteamaMeter\Http\Requests;

use GuzzleHttp\Client;
use Inensus\SteamaMeter\Helpers\ApiHelpers;
use Inensus\SteamaMeter\Models\SteamaCredential;
use Inensus\StemaMeter\Exceptions\ModelNotFoundException;

class SteamaMeterApiRequests
{
    private $client;
    private $apiHelpers;
    private $credential;


    public function __construct(
        Client $httpClient,
        ApiHelpers $apiHelpers,
        SteamaCredential $credentialModel
    ) {
        $this->client = $httpClient;
        $this->apiHelpers = $apiHelpers;
        $this->credential = $credentialModel;
    }

    public function get($url)
    {
        try {
            $credential = $this->getCredentials();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException($e->getMessage());
        }
        $request = $this->client->get(
            $credential->api_url . $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authorization' => 'Token ' . $credential->authentication_token
                ],
            ]
        );
        return $this->apiHelpers->CheckApiResult(json_decode((string)$request->getBody(), true));
    }
    public function token($url, $postParams)
    {
        try {
            $credential = $this->getCredentials();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException($e->getMessage());
        }

        $request = $this->client->post(
            $credential->api_url . $url,
            [
                'body' => json_encode($postParams),
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                ],
            ]
        );
        return $this->apiHelpers->CheckApiResult(json_decode((string)$request->getBody(), true));
    }
    public function post($url, $postParams)
    {
        try {
            $credential = $this->getCredentials();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException($e->getMessage());
        }
        $request = $this->client->post(
            $credential->api_url . $url,
            [
                'body' => json_encode($postParams),
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authorization' => 'Token ' . $credential->authentication_token
                ],
            ]
        );
        return $this->apiHelpers->CheckApiResult(json_decode((string)$request->getBody(), true));
    }

    public function put($url, $putParams)
    {
        try {
            $credential = $this->getCredentials();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException($e->getMessage());
        }
        $request = $this->client->put(
            $credential->api_url . $url,
            [
                'body' => json_encode($putParams),
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authorization' => 'Token ' . $credential->authentication_token
                ],
            ]
        );
        return $this->apiHelpers->CheckApiResult(json_decode((string)$request->getBody(), true));
    }

    public function patch($url, $putParams)
    {
        try {
            $credential = $this->getCredentials();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException($e->getMessage());
        }
        $request = $this->client->patch(
            $credential->api_url . $url,
            [
                'body' => json_encode($putParams),
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authorization' => 'Token ' . $credential->authentication_token
                ],
            ]
        );
        return $this->apiHelpers->CheckApiResult(json_decode((string)$request->getBody(), true));
    }

    public function getByParams($url, $params)
    {
        try {
            $credential = $this->getCredentials();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException($e->getMessage());
        }
        $apiUrl = $credential->api_url . $url . '?';
        foreach ($params as $key => $value) {
            $apiUrl .= $key . "=" . $value . "&";
        }
        $apiUrl = substr($apiUrl, 0, -1);

        $request = $this->client->get(
            $apiUrl,
            [
                'headers' => [
                    'Content-Type' => 'application/json;charset=utf-8',
                    'Authorization' => 'Token ' . $credential->authentication_token
                ],
            ]
        );
        return json_decode((string)$request->getBody(), true);
    }

    public function getCredentials()
    {
        return $this->credential->newQuery()->firstOrFail();
    }
}
