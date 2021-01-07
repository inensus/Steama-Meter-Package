<?php


namespace Inensus\SteamaMeter\Services;


use App\Models\ConnectionType;
use Inensus\SteamaMeter\Models\SteamaUserType;

class SteamaUserTypeService
{
    private $connectionType;
    private $userType;

    public function __construct(ConnectionType $connectionTypeModel, SteamaUserType $userTypeModel)
    {
        $this->connectionType = $connectionTypeModel;
        $this->userType = $userTypeModel;
    }

    /**
     * This function uses one time on installation of the package.
     *
     */
    public function createUserTypes()
    {
        $connectionTypes = [
            'NA' => 'Not Specified',
            'RES' => 'Residential',
            'BUS' => 'Business',
            'INS' => 'Institution'
        ];
        foreach ($connectionTypes as $key => $value) {
            $connectionType = $this->connectionType->newQuery()->create([
                'name' => $value
            ]);
            $this->userType->newQuery()->create([
                'mpm_connection_type_id' => $connectionType->id,
                'name' => $value,
                'syntax' => $key
            ]);

        }

    }
}