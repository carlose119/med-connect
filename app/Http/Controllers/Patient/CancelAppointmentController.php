<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\States\Appointment\Cancelled;
use Illuminate\Http\RedirectResponse;

class CancelAppointmentController extends Controller
{
    /**
     * Cancel an appointment within the 24h window.
     * The 24h assertion in the controller is a pre-check; the
     * CancelAppointmentTransition also enforces it for defense in depth.
     */
    public function __invoke(Appointment $appointment): RedirectResponse
    {
        $this->authorize('cancel', $appointment);

        // 24h window pre-check
        if ($appointment->start_time <= now()->addHours(24)) {
            return back()->withErrors([
                'cancellation' => 'Cannot cancel an appointment within 24 hours of the start time.',
            ]);
        }

        try {
            $appointment->state->transitionTo(Cancelled::class, auth()->user());
        } catch (\Exception $e) {
            return back()->withErrors([
                'cancellation' => $e->getMessage(),
            ]);
        }

        return redirect(route('patient.dashboard'))
            ->with('status', 'Appointment cancelled successfully.');
    }
}
