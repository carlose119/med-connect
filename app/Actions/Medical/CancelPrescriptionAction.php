<?php

namespace App\Actions\Medical;

use App\Models\Prescription;

/**
 * Cancel an active prescription.
 *
 * Sets status to 'cancelled' and records the cancellation reason.
 * Only active prescriptions can be cancelled.
 *
 * @throws \InvalidArgumentException if the prescription is not active
 */
class CancelPrescriptionAction
{
    public function __invoke(Prescription $prescription, ?string $reason = null): Prescription
    {
        if ($prescription->status === 'cancelled') {
            throw new \InvalidArgumentException(
                'Cannot cancel a prescription that is already cancelled.'
            );
        }

        $prescription->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        return $prescription->fresh();
    }
}