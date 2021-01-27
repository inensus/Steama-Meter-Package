<?php


namespace Inensus\SteamaMeter\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Http\Requests\SteamaMeterApiRequests;
use Inensus\SteamaMeter\Models\SteamaCredential;

class SteamaCredentialService
{
    private $rootUrl = '/get-token/';
    private $credential;
    private $steamaApi;

    public function __construct(
        SteamaCredential $credentialModel,
        SteamaMeterApiRequests $steamaApi
    ) {
        $this->credential = $credentialModel;
        $this->steamaApi = $steamaApi;
    }

    /**
     * This function uses one time on installation of the package.
     *
     */
    public function createCredentials()
    {
        $credentials = $this->credential->newQuery()->first();
        if (!$credentials){
            return $this->credential->newQuery()->create();
        }
        return $credentials;
    }

    public function getCredentials()
    {

            return $this->credential->newQuery()->first();
    }

    public function updateCredentials($data)
    {
        $credential = $this->credential->newQuery()->find($data['id']);
        try {

            $credential->update([
                'username' => $data['username'],
                'password' => $data['password'],

            ]);
            $postParams = [
                'username' => $data['username'],
                'password' => $data['password'],
            ];
            $result = $this->steamaApi->token($this->rootUrl, $postParams);

            $credential->update([
                'authentication_token' => $result['token'],
                'is_authenticated'=>true
            ]);

             return $credential->fresh();
        } catch (Exception $e) {
            $credential->update([
                'authentication_token' => null,
                'is_authenticated'=>false
            ]);

            Log::critical('Error while updating steama credentials', ['message' => $e->getMessage()]);
            throw new Exception($e->getMessage());
        }
    }
}