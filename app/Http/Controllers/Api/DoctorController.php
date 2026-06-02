<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListDoctorsRequest;
use App\Http\Resources\Api\DoctorResource;
use App\Models\Doctor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the doctors endpoint group.
 *
 * `index()`  → GET    /api/doctors              (Eloquent, paginated)
 * `slots()`  → GET    /api/doctors/{id}/slots   (DoctorAvailabilityService)
 *
 * Both methods wrap the agenda-core domain: doctors are public
 * (Sanctum auth required, but no per-row authorization).
 */
class DoctorController extends Controller
{
    /**
     * GET /api/doctors — paginated list, filterable by specialty_id.
     *
     * The `user` and `specialty` relations are eager-loaded to avoid
     * an N+1 when DoctorResource renders the sub-objects.
     */
    public function index(ListDoctorsRequest $request): AnonymousResourceCollection
    {
        $query = Doctor::query()->with(['user', 'specialty']);
        $filters = $request->validated();

        if (! empty($filters['specialty_id'])) {
            $query->where('specialty_id', (int) $filters['specialty_id']);
        }

        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $query->whereHas('user', function ($sub) use ($q): void {
                $sub->where('name', 'LIKE', '%'.$q.'%');
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $query
            ->orderBy('id')
            ->paginate($perPage);

        return DoctorResource::collection($paginator);
    }
}
