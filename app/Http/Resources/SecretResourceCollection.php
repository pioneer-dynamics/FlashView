<?php

namespace App\Http\Resources;

use App\Models\Secret;
use mathewparet\LaravelPolicyAbilitiesExport\Resources\ResourceCollectionWithPermissions;

class SecretResourceCollection extends ResourceCollectionWithPermissions
{
    /**
     * @var \Illuminate\Database\Eloquent\Model|string
     */
    protected $model = Secret::class;
}
