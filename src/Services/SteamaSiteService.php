<?php


namespace Inensus\SteamaMeter\Services;


use App\Models\City;
use App\Models\Cluster;
use App\Models\GeographicalInformation;
use App\Models\MiniGrid;
use Illuminate\Support\Facades\Log;
use Inensus\SteamaMeter\Helpers\ApiHelpers;
use Inensus\SteamaMeter\Http\Requests\SteamaMeterApiRequests;
use Inensus\SteamaMeter\Models\SteamaSite;
use Exception;

class SteamaSiteService implements ISynchronizeService
{
    private $site;
    private $steamaApi;
    private $apiHelpers;
    private $rootUrl = '/sites';
    private $miniGrid;
    private $cluster;
    private $geographicalInformation;
    private $city;
    public function __construct(
        SteamaSite $steamaSiteModel,
        SteamaMeterApiRequests $steamaApi,
        ApiHelpers $apiHelpers,
        MiniGrid $miniGrid,
        Cluster $cluster,
        GeographicalInformation $geographicalInformation,
        City $city
    ) {
        $this->site = $steamaSiteModel;
        $this->steamaApi = $steamaApi;
        $this->apiHelpers = $apiHelpers;
        $this->miniGrid = $miniGrid;
        $this->cluster = $cluster;
        $this->city=$city;
        $this->geographicalInformation=$geographicalInformation;
    }

    public function getSites($request)
    {
        $perPage = $request->input('per_page') ?? 15;
        return $this->site->newQuery()->with('mpmMiniGrid.location')->paginate($perPage);
    }

    public function getSitesCount()
    {
        return count($this->site->newQuery()->get());
    }

    public function sync()
    {
        try {
            $syncCheck = $this->syncCheck(true);
            if (!$syncCheck['result']) {
                $sites = $syncCheck['data'];
                foreach ($sites as $key => $site) {
                    $registeredStmSite = $this->site->newQuery()->where('site_id', $site['id'])->first();
                    $stmSiteHash = $this->steamaSiteHasher($site);

                    if ($registeredStmSite) {
                        $isHashChanged = $registeredStmSite->hash === $stmSiteHash ? false : true;
                        $relatedMiniGrid = $this->miniGrid->newQuery()->find($registeredStmSite->mpm_mini_grid_id);

                        if (!$relatedMiniGrid) {
                            $miniGrid = $this->creteRelatedMiniGrid($site);
                            $registeredStmSite->update([
                                'site_id'=>$site['id'],
                                'mpm_mini_grid_id'=>$miniGrid->id,
                                'hash'=>$stmSiteHash,
                            ]);
                            $this->updateGeographicalInformation($miniGrid->id,$site);
                        } else if ($relatedMiniGrid && $isHashChanged) {
                                $miniGrid = $this->updateRelatedMiniGrid($site,$relatedMiniGrid);
                                $this->updateGeographicalInformation($miniGrid->id,$site);
                                $registeredStmSite->update([
                                    'site_id'=>$site['id'],
                                    'mpm_mini_grid_id'=>$miniGrid->id,
                                    'hash'=>$stmSiteHash,
                                ]);
                            } else {
                                continue;
                            }
                    } else {
                        $miniGrid = $this->creteRelatedMiniGrid($site);
                        $this->site->newQuery()->create([
                            'site_id'=>$site['id'],
                            'mpm_mini_grid_id'=>$miniGrid->id,
                            'hash'=>$stmSiteHash,
                        ]);
                        $this->updateGeographicalInformation($miniGrid->id,$site);
                    }
                }
            }
            return $this->site->newQuery()->with('mpmMiniGrid.location')->paginate(config('steama.paginate'));
        } catch (Exception $e) {
            Log::critical('Steama sites sync failed.', ['Error :' => $e->getMessage()]);
            throw  new Exception ($e->getMessage());
        }
    }

    public function syncCheck($returnData = false)
    {
        try {
            $url = $this->rootUrl . '?page=1&page_size=100';
            $result = $this->steamaApi->get($url);
            $sites = $result['results'];
            while ($result['next']) {
                $url = $this->rootUrl .'?' . explode('?', $result['next'])[1];
                $result = $this->steamaApi->get($url);
                foreach ($result['results'] as $site){
                    array_push($sites, $site);
                }
            }

            $stmSites = $this->site->newQuery()->get();
            $stmSitesCount = count($stmSites);
            $sitesCount = count($sites);
            if ($stmSitesCount === $sitesCount) {
                foreach ($sites as $key => $site) {
                    $registeredStmSite = $this->site->newQuery()->where('site_id', $site['id'])->first();

                    if ($registeredStmSite) {
                        $siteHash = $this->steamaSiteHasher($site);
                        $stmSiteHash = $registeredStmSite->hash;
                        if ($siteHash !== $stmSiteHash) {

                            break;
                        } else {
                            $sitesCount--;
                        }
                    } else {
                        break;
                    }

                }
                if ($sitesCount === 0) {
                    return $returnData ? ['data' => $sites, 'result' => true] : ['result' => true];
                }
                return $returnData ? ['data' => $sites, 'result' => false] : ['result' => false];
            } else {
                return $returnData ? ['data' => $sites, 'result' => false] : ['result' => false];
            }

        } catch (Exception $e) {
            if ($returnData) {
                return ['result' => false];
            }
            throw  new Exception ($e->getMessage());
        }
    }

    public function creteRelatedMiniGrid($site)
    {
        $cluster = $this->cluster->newQuery()->latest('created_at')->first();
        $miniGrid= $this->miniGrid->newQuery()->create([
            'name' => $site['name'],
            'cluster_id' => $cluster->id
        ]);
            $this->city->newQuery()->create([
                'name'=>'Steama City',
                'mini_grid_id'=>$miniGrid->id,
                'cluster_id'=>$miniGrid->cluster->id
            ]);
         return $miniGrid;
    }

    public function updateRelatedMiniGrid($site,$miniGrid)
    {
        $miniGrid->newQuery()->update([
            'name' => $site['name'],
        ]);
        return $miniGrid->fresh();
     }
    public function updateGeographicalInformation($miniGridId,$site)
    {
        $geographicalInformation = $this->geographicalInformation->newQuery()->whereHasMorph('owner', [MiniGrid::class],
            static function ($q) use ($miniGridId) {
                $q->where('id', $miniGridId);
            })->first();
        $points= $site['latitude']===null?config('steama.geoLocation'):$site['latitude'].','.$site['longitude'];
        $geographicalInformation->update([
            'points'=>$points
        ]);
    }

    public function checkLocationAvailability()
    {
        return $this->cluster->newQuery()->latest('created_at')->first();
    }

    private function steamaSiteHasher($steamaSite)
    {
        return $this->apiHelpers->makeHash([
            $steamaSite['name'],
            $steamaSite['latitude'],
            $steamaSite['longitude'],
            $steamaSite['num_meters']
        ]);
    }
}