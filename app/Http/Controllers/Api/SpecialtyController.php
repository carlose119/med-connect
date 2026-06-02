<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\SpecialtyResource;
use App\Models\Specialty;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the specialties endpoint.
 *
 * `index()`  → GET /api/specialties
 *
 * The list is small (handful of active specialties at any time)
 * and is not paginated per design §3. Order is by name ASC.
 */
class SpecialtyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $specialties = Specialty::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return SpecialtyResource::collection($specialties);
    }
}
