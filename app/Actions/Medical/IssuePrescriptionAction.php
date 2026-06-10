<?php

namespace App\Actions\Medical;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\States\Appointment\Completed;
use Illuminate\Support\Facades\DB;

/**
 * Issue a prescription for a completed appointment.
 *
 * Creates a prescription with ordered items within a single DB transaction.
 * Validates the appointment is in 'completed' state before persisting.
 *
 * @throws \InvalidArgumentException if the appointment is not completed
 */
class IssuePrescriptionAction
{
    public function __invoke(Appointment $appointment, Doctor $doctor, array $items): Prescription
    {
        if (! $appointment->state instanceof Completed) {
            throw new \InvalidArgumentException(
                "Cannot issue a prescription for an appointment that is not completed. Current state: " . $appointment->state::class
            );
        }

        return DB::transaction(function () use ($appointment, $doctor, $items) {
            $prescription = Prescription::create([
                'appointment_id' => $appointment->id,
                'doctor_id' => $doctor->id,
                'patient_id' => $appointment->patient_id,
                'unique_code' => (new GeneratePrescriptionCodeAction())->generate(),
                'issued_at' => now(),
                'status' => 'active',
            ]);

            foreach ($items as $index => $item) {
                PrescriptionItem::create([
                    'prescription_id' => $prescription->id,
                    'name' => $item['name'],
                    'dosage' => $item['dosage'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'position' => $index + 1, // 1-based index from array order
                ]);
            }

            return $prescription->load('items');
        });
    }
}