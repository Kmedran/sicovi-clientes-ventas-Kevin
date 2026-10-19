<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Warehouse;
use RuntimeException;

class OrganizationContext
{
    public function company(): Company
    {
        return Company::query()->sole();
    }

    public function branch(): Branch
    {
        $branch = $this->company()->branches()->sole();
        if (! $branch->active) {
            throw new RuntimeException('La sucursal inicial debe estar activa.');
        }

        return $branch;
    }

    public function warehouse(): Warehouse
    {
        $warehouse = $this->branch()->warehouses()->sole();
        if (! $warehouse->active) {
            throw new RuntimeException('La bodega inicial debe estar activa.');
        }

        return $warehouse;
    }
}
