<?php

namespace App\Http\Resources;

use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use mathewparet\LaravelPolicyAbilitiesExport\Resources\ResourceCollectionWithPermissions;

class SecretResourceCollection extends ResourceCollectionWithPermissions
{
    /**
     * @var \Illuminate\Database\Eloquent\Model|string $model 
     */
    protected $model = Secret::class;
}