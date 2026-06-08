<?php

namespace App\Livewire\Patient;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\DoctorAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.patient')]
#[Title('Book Appointment')]
class BookAppointment extends Component
{
    #[Locked]
    public Doctor $doctor;

    public string $selectedDate = '';

    public string $selectedSlot = '';

    public array $availableSlots = [];

    public function mount(Doctor $doctor): void
    {
        $this->doctor = $doctor->load(['user', 'specialty']);
    }

    public function loadSlots(): void
    {
        $this->validate([
            'selectedDate' => ['required', 'date', 'after_or_equal:' . now()->format('Y-m-d'), 'before_or_equal:' . now()->addDays(7)->format('Y-m-d')],
        ]);

        $date = CarbonImmutable::parse($this->selectedDate);
        $slots = app(DoctorAvailabilityService::class)->slots($this->doctor->id, $date);

        $this->availableSlots = $slots;
    }

    public function book(): void
    {
        $this->validate([
            'selectedSlot' => ['required', 'string'],
        ]);

        $start = CarbonImmutable::parse($this->selectedSlot);
        $patient = auth()->user()->patient;

        if ($start->lt(now()->addHours(2))) {
            $this->addError('selectedSlot', 'Bookings require at least 2 hours of anticipation.');
            return;
        }

        $localStart = $start->copy()->setTimezone(config('app.timezone'));
        $dayOfWeek = (int) $localStart->dayOfWeekIso;
        $startUtc = $start->copy()->setTimezone('UTC');

        // Validate: slot availability + patient overlap (outside transaction)
        $availableSlots = app(DoctorAvailabilityService::class)->slots($this->doctor->id, $start);
        $isAvailable = collect($availableSlots)->contains(
            fn (array $slot) => $slot['start']->equalTo($startUtc->toImmutable())
        );

        if (! $isAvailable) {
            $this->addError('selectedSlot', 'This slot is no longer available. Please select another time.');
            return;
        }

        $rule = DoctorSchedule::query()
            ->where('doctor_id', $this->doctor->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();
        $slotMinutes = (int) ($rule?->slot_duration_minutes ?? 30);
        $endUtc = $startUtc->copy()->addMinutes($slotMinutes);

        $overlap = Appointment::query()
            ->where('patient_id', $patient->id)
            ->whereIn('state', ['pending', 'confirmed', 'completed', 'no_show'])
            ->where('start_time', '<', $endUtc)
            ->where('end_time', '>', $startUtc)
            ->exists();

        if ($overlap) {
            $this->addError('selectedSlot', 'You already have an appointment overlapping this time slot.');
            return;
        }

        // INSERT with lockForUpdate in a transaction
        DB::transaction(function () use ($startUtc, $endUtc, $patient, $dayOfWeek) {
            DoctorSchedule::query()
                ->where('doctor_id', $this->doctor->id)
                ->where('day_of_week', $dayOfWeek)
                ->lockForUpdate()
                ->get();

            Appointment::create([
                'doctor_id' => $this->doctor->id,
                'patient_id' => $patient->id,
                'start_time' => $startUtc,
                'end_time' => $endUtc,
                'state' => 'pending',
            ]);
        });

        $this->redirect(route('patient.dashboard'), navigate: true);
    }

    public function render(): View
    {
        $minDate = now()->format('Y-m-d');
        $maxDate = now()->addDays(7)->format('Y-m-d');

        return view('patient.book', [
            'doctor' => $this->doctor,
            'minDate' => $minDate,
            'maxDate' => $maxDate,
        ]);
    }
}
