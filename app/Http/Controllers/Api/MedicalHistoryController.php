<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\MedicalHistoryResource;
use App\Models\MedicalHistory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;

/**
 * HTTP surface for the medical histories endpoint group.
 *
 * `show()`  → GET /api/medical-histories/{medical_history}
 *
 * Authz is delegated to MedicalHistoryPolicy@view.
 */
class MedicalHistoryController extends Controller
{
    use AuthorizesRequests;

    public function show(MedicalHistory $medicalHistory): MedicalHistoryResource
    {
        $this->authorize('view', $medicalHistory);

        $medicalHistory->loadCount('notes');

        return new MedicalHistoryResource($medicalHistory);
    }
}
