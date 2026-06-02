<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ListDoctorsRequest;
use App\Http\Requests\Api\ListSlotsRequest;
use App\Http\Resources\Api\DoctorResource;
use App\Http\Resources\Api\SlotResource;
use App\Models\Doctor;
use App\Services\DoctorAvailabilityService;
use Carbon\CarbonImmutable;
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

    /**
     * GET /api/doctors/{doctor}/slots — available slots for a date.
     *
     * Wraps the pure DoctorAvailabilityService::slots() function.
     * The resolved TZ (from `?tz=` or the clinic default) is read
     * inside SlotResource; we pass the service the local date and
     * the service's own TZ argument (it falls back to the app
     * timezone when null, which is what we want here).
     */
    public function slots(ListSlotsRequest $request, Doctor $doctor): AnonymousResourceCollection
    {
        $tzName = (string) ($request->attributes->get('tz')?->name ?? config('app.timezone'));

        $date = CarbonImmutable::createFromFormat('Y-m-d', (string) $request->validated('date'))
            ->setTimezone($tzName);

        $slots = app(DoctorAvailabilityService::class)->slots(
            $doctor->id,
            $date,
            $tzName,
        );

        return SlotResource::collection(collect($slots));
    }
}
