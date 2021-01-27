<?php


namespace Inensus\SteamaMeter\Services;


use App\Http\Services\AddressService;
use App\Models\Address\Address;
use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\Person\Person;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Helpers\ApiHelpers;
use Inensus\SteamaMeter\Http\Requests\SteamaMeterApiRequests;
use Inensus\SteamaMeter\Models\SteamaAgent;
use Exception;
use Inensus\SteamaMeter\Models\SteamaSite;

class SteamaAgentService implements ISynchronizeService
{
    private $agentCommission;
    private $agent;
    private $stmAgent;
    private $steamaApi;
    private $apiHelpers;
    private $rootUrl = '/agents';
    private $person;
    private $addressService;
    private $site;
    private $address;

    public function __construct(
        AgentCommission $agentCommissionModel,
        SteamaAgent $steamaAgentModel,
        SteamaMeterApiRequests $steamaApi,
        ApiHelpers $apiHelpers,
        Agent $agent,
        Person $person,
        AddressService $addressService,
        SteamaSite $site,
         Address $address
    ) {
        $this->agentCommission = $agentCommissionModel;
        $this->stmAgent = $steamaAgentModel;
        $this->steamaApi = $steamaApi;
        $this->apiHelpers = $apiHelpers;
        $this->agent = $agent;
        $this->person = $person;
        $this->addressService = $addressService;
        $this->site = $site;
        $this->address=$address;
    }

    public function getAgents($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return $this->stmAgent->newQuery()->with(['mpmAgent.person.addresses','site.mpmMiniGrid'])->paginate($perPage);
    }
    public function getAgentsCount()
    {
        return count($this->agent->newQuery()->get());
    }
    /**
     * This function uses one time on installation of the package.
     *
     */
    public function createSteamaAgentCommission()
    {
        $agentCommission = $this->agentCommission->newQuery()->where('name', 'Steama Agent Comission')->first();
        if (!$agentCommission) {
            $agentCommission = $this->agentCommission->newQuery()->create([
                'name' => 'Steama Agent Comission',
                'energy_commission' => 0,
                'appliance_commission' => 0,
                'risk_balance' => -99999999999
            ]);
        }
        return $agentCommission;
    }

    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);
            if (!$syncCheck['result']) {
                $agents = $syncCheck['data'];
                foreach ($agents as $key => $agent) {
                    $registeredStmAgent = $this->stmAgent->newQuery()->where('agent_id',
                        $agent['id'])->first();
                    $stmAgentHash = $this->steamaAgentHasher($agent);

                    if ($registeredStmAgent) {
                        $isHashChanged = $registeredStmAgent->hash === $stmAgentHash ? false : true;
                        $relatedAgent = $this->agent->newQuery()->where('id',
                            $registeredStmAgent->mpm_agent_id)->first();
                        if (!$relatedAgent) {
                            $newAgent = $this->createRelatedAgent($agent);
                            $registeredStmAgent->update([
                                'agent_id' => $agent['id'],
                                'mpm_agent_id' => $newAgent->id,
                                'site_id' => $agent['site'],
                                'is_credit_limited' => $agent['is_credit_limited'],
                                'credit_balance' => $agent['credit_balance'],
                                'hash' => $stmAgentHash
                            ]);
                        } else {
                            if ($relatedAgent && $isHashChanged) {
                                $this->updateRelatedAgent($agent, $relatedAgent);
                                $registeredStmAgent->update([
                                    'agent_id' => $agent['id'],
                                    'mpm_agent_id' => $relatedAgent->id,
                                    'site_id' => $agent['site'],
                                    'is_credit_limited' => $agent['is_credit_limited'],
                                    'credit_balance' => $agent['credit_balance'],
                                    'hash' => $stmAgentHash
                                ]);
                            } else {
                                continue;
                            }
                        }
                    } else {
                        $newAgent = $this->createRelatedAgent($agent);
                        $this->stmAgent->newQuery()->create([
                            'agent_id' => $agent['id'],
                            'mpm_agent_id' => $newAgent->id,
                            'site_id' => $agent['site'],
                            'is_credit_limited' => $agent['is_credit_limited'],
                            'credit_balance' => $agent['credit_balance'],
                            'hash' => $stmAgentHash
                        ]);
                    }
                }
            }
            return $this->stmAgent->newQuery()->with([
                'mpmAgent.person.addresses',
                'site.mpmMiniGrid'
            ])->paginate(config('steama.paginate'));
        } catch (Exception $e) {
            Log::critical('Steama agents sync failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }

    public function syncCheck($returnData = false)
    {
        try {
            $url = $this->rootUrl . '?page=1&page_size=100';
            $result = $this->steamaApi->get($url);
            $agents = $result['results'];
            while ($result['next']) {
                $url = $this->rootUrl . '?' . explode('?', $result['next'])[1];
                $result = $this->steamaApi->get($url);
                foreach ($result['results'] as $agent) {
                    array_push($agents, $agent);
                }
            }
            $stmAgents = $this->stmAgent->newQuery()->get();
            $stmAgentsCount = count($stmAgents);
            $agentsCount = count($agents);


            if ($stmAgentsCount === $agentsCount) {
                foreach ($agents as $key => $agent) {
                    $registeredStmAgent = $this->stmAgent->newQuery()->where('agent_id', $agent['id'])->first();
                    if ($registeredStmAgent) {
                        $agentHash = $this->steamaAgentHasher($agent);
                        $stmAgentHash = $registeredStmAgent->hash;
                        if ($stmAgentHash !== $agentHash) {
                            break;
                        } else {
                            $agentsCount--;
                        }
                    } else {

                        break;
                    }
                }

                if ($agentsCount === 0) {
                    return $returnData ? ['data' => $agents, 'result' => true] : ['result' => true];
                }
                return $returnData ? ['data' => $agents, 'result' => false] : ['result' => false];
            } else {
                return $returnData ? ['data' => $agents, 'result' => false] : ['result' => false];
            }
        } catch (Exception $e) {

            if ($returnData) {
                return ['result' => false];
            }
            throw  new Exception ($e->getMessage());
        }
    }

    public function createRelatedAgent($stmAgent)
    {

        $person = $this->person->newQuery()->create([
            'name' => $stmAgent['first_name'],
            'surname' => $stmAgent['last_name'],
            'is_customer' => 0
        ]);
        $site = $this->site->newQuery()->with('mpmMiniGrid.cities')->where('site_id', $stmAgent['site'])->first();

        $city = $site->mpmMiniGrid->cities->first();

        $addressService = App::make(AddressService::class);
        $addressParams = [
            'city_id' => $city->id,
            'email' => "",
            'phone' => $stmAgent['telephone'],
            'street' => $stmAgent['site_name'],
            'is_primary' => 1,
        ];

        $address = $addressService->instantiate($addressParams);
        $agentCommission = $this->agentCommission->newQuery()->where('name', 'Steama Agent Comission')->first();
        $counter = count($this->stmAgent->newQuery()->get());

        $agent = $this->agent->newQuery()->create([
            'person_id' => $person->id,
            'name' => $person->name,
            'password' => $stmAgent['first_name'] . $stmAgent['last_name'],
            'email' => 'StmAgent' . strval($counter + 1) . 'steama.co',
            'mini_grid_id' => $site->mpmMiniGrid->id,
            'agent_commission_id' => $agentCommission->id
        ]);
        $addressService->assignAddressToOwner($person, $address);
        return $agent;
    }

    public function updateRelatedAgent($stmAgent, $agent)
    {
        $relatedPerson = $agent->person();
        $relatedPerson->update([
            'name' => $stmAgent['first_name'],
            'surname' => $stmAgent['last_name'],
        ]);
        $site = $this->site->newQuery()->with('mpmMiniGrid.cities')->where('name', $stmAgent['site_name'])->first();
        $city = $site->mpmMiniGrid->cities->first();
        $address = $this->address->newQuery()->where('owner_type', 'person')->where('owner_id',
            $relatedPerson->id)->where('is_primary', 1)->first();
        $address->update([
            'city_id' => $city->id,
            'phone' => $stmAgent['telephone'],
            'street' => $stmAgent['site_name'],
            'is_primary' => 1,
        ]);
        $agent->update([
            'name' => $relatedPerson->name,
            'password' => $stmAgent['first_name'] . $stmAgent['last_name'],
            'mini_grid_id' => $site->mpmMiniGrid->id,
        ]);
    }

    private function steamaAgentHasher($steamaAgent)
    {
        return $this->apiHelpers->makeHash([
            $steamaAgent['first_name'],
            $steamaAgent['last_name'],
            $steamaAgent['telephone'],
            $steamaAgent['site'],
            $steamaAgent['is_credit_limited'],
            $steamaAgent['credit_balance'],
        ]);
    }
}