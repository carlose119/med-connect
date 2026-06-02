<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\PatientResource;
use App\Models\Patient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the patients endpoint group.
 *
 * `show()`  → GET /api/patients/{patient}
 *
 * Authz is the existing PatientPolicy@view from agenda-core PR 3:
 *   - admin          → 200
 *   - doctor (any)   → 200
 *   - self patient   → 200
 *   - other patient  → 403 FORBIDDEN
 */
class PatientController extends Controller
{
    use AuthorizesRequests;

    public function show(Patient $patient): PatientResource
    {
        $this->authorize('view', $patient);

        return new PatientResource($patient->loadMissing('user'));
    }
}
