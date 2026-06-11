<?php

namespace App\Http\Controllers\Doctor;

use App\Actions\Medical\IssuePrescriptionAction;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\MedicalHistory;
use App\Models\MedicalNote;
use App\Models\Patient;
use App\Models\User;
use App\States\Appointment\Completed;
use App\States\Appointment\Confirmed;
use App\States\Appointment\Pending;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ConsultationController extends Controller
{
    public function show(int $patient_id): View|RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = auth()->user();
        /** @var Doctor $doctor */
        $doctor = $currentUser->doctor;
        abort_unless($doctor, 403, 'Solo médicos pueden acceder a esta página.');

        $patient = Patient::with('user')->findOrFail($patient_id);

        $medicalHistory = MedicalHistory::firstOrCreate(
            ['patient_id' => $patient->id],
            ['primary_doctor_id' => $doctor->id, 'opened_at' => now()],
        );

        $walkInAppointment = Appointment::firstOrCreate(
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
            ],
            [
                'start_time' => now(),
                'end_time' => now()->addMinutes(30),
                'state' => 'pending',
                'notes' => 'Consulta sin cita',
            ],
        );

        $previousNotes = $medicalHistory?->notes()->with('doctor.user')->orderByDesc('created_at')->get() ?? collect();

        return view('doctor.consultation', [
            'patient' => $patient,
            'walkInAppointment' => $walkInAppointment,
            'medicalHistory' => $medicalHistory,
            'previousNotes' => $previousNotes,
            'prescriptionItems' => [[]],
        ]);
    }

    public function saveNote(Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $currentUser = auth()->user();
        /** @var Doctor $doctor */
        $doctor = $currentUser->doctor;
        abort_unless($doctor, 403, 'Solo médicos pueden acceder a esta página.');

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'symptoms' => 'nullable|string',
            'physical_exam' => 'nullable|string',
            'diagnosis' => 'required|string',
            'treatment_notes' => 'nullable|string',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $medicalHistory = MedicalHistory::firstOrCreate(
            ['patient_id' => $patient->id],
            ['primary_doctor_id' => $doctor->id, 'opened_at' => now()],
        );

        $walkInAppointment = Appointment::firstOrCreate(
            [
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
            ],
            [
                'start_time' => now(),
                'end_time' => now()->addMinutes(30),
                'state' => 'pending',
                'notes' => 'Consulta sin cita',
            ],
        );

        MedicalNote::create([
            'medical_history_id' => $medicalHistory->id,
            'appointment_id' => $walkInAppointment->id,
            'doctor_id' => $doctor->id,
            'symptoms' => $validated['symptoms'] ?? null,
            'physical_exam' => $validated['physical_exam'] ?? null,
            'diagnosis' => $validated['diagnosis'],
            'treatment_notes' => $validated['treatment_notes'] ?? null,
        ]);

        return redirect()->back()->with('status', 'Nota clínica guardada correctamente.');
    }

    public function confirmAppointment(int $patient_id): RedirectResponse
    {
        /** @var User $currentUser */
        $doctor = auth()->user()->doctor;
        abort_unless($doctor, 403);

        $appointment = Appointment::where('patient_id', $patient_id)
            ->where('doctor_id', $doctor->id)
            ->first();
        abort_unless($appointment, 403);

        $appointment->state->transitionTo(Confirmed::class, auth()->user());

        return redirect()->back()->with('status', 'Cita confirmada.');
    }

    public function completeConsultation(int $patient_id): RedirectResponse
    {
        /** @var User $currentUser */
        $doctor = auth()->user()->doctor;
        abort_unless($doctor, 403);

        $appointment = Appointment::where('patient_id', $patient_id)
            ->where('doctor_id', $doctor->id)
            ->first();
        abort_unless($appointment, 403);

        try {
            $appointment->state->transitionTo(Completed::class, auth()->user());
            return redirect()->back()->with('status', 'Consulta completada. Ya puede emitir una receta.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'No se pudo completar. La cita debe estar en estado confirmado.');
        }
    }

    public function issuePrescription(Request $request): RedirectResponse
    {
        /** @var User $currentUser */
        $doctor = auth()->user()->doctor;
        abort_unless($doctor, 403);

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'prescriptionItems' => 'required|array',
        ]);

        $items = array_values(array_filter($validated['prescriptionItems'], fn($item) => !empty($item['name'])));

        if (empty($items)) {
            return redirect()->back()->with('error', 'Debe agregar al menos un medicamento.');
        }

        $appointment = Appointment::where('patient_id', $validated['patient_id'])
            ->where('doctor_id', $doctor->id)
            ->first();

        if (!$appointment || !$appointment->state instanceof Completed) {
            return redirect()->back()->with('error', 'La cita debe estar completada.');
        }

        try {
            $action = new IssuePrescriptionAction();
            $prescription = $action($appointment, $doctor, $items);
            return redirect()->back()->with('status', "Receta {$prescription->unique_code} creada.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'No se pudo emitir la receta.');
        }
    }
}