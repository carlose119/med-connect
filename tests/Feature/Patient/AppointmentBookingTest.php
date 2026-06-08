<?php

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use Carbon\CarbonImmutable;
use Database\Factories\DoctorScheduleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create a doctor with a schedule for "now + 3 days at 09:00-12:00".
    // The 3-day buffer guarantees the 2h anticipation check passes.
    $this->scheduleDay = CarbonImmutable::now()->addDays(3);
    $dayOfWeek = (int) $this->scheduleDay->dayOfWeekIso;

    $specialty = Specialty::factory()->create(['name' => 'Cardiology']);
    $this->doctor = Doctor::factory()->create(['specialty_id' => $specialty->id]);

    DoctorScheduleFactory::new()
        ->for($this->doctor)
        ->create([
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

    // Authenticated patient
    $this->patient = Patient::factory()->create();
    $this->user = $this->patient->user;
});

it('creates an appointment with status pending on the happy path', function (): void {
    $this->actingAs($this->user);

    $selectedDate = $this->scheduleDay->format('Y-m-d');

    /** @var \Livewire\Testing\TestableLivewire $component */
    $component = Livewire::test(\App\Livewire\Patient\BookAppointment::class, [
        'doctor' => $this->doctor,
    ]);

    // Load slots
    $component->set('selectedDate', $selectedDate);
    $component->call('loadSlots');

    // Assert slots are available
    $slots = $component->get('availableSlots');
    expect($slots)->toBeArray();
    expect($slots)->not->toBeEmpty();

    // Select the first slot and book
    $firstSlot = $slots[0]['start']->toIso8601String();
    $component->set('selectedSlot', $firstSlot);
    $component->call('book');

    // Assert redirected to dashboard
    $component->assertRedirect(route('patient.dashboard'));

    // Assert appointment was created
    $appointment = Appointment::query()
        ->where('doctor_id', $this->doctor->id)
        ->where('patient_id', $this->patient->id)
        ->first();

    expect($appointment)->not->toBeNull();
    expect($appointment->state)->toBe('pending');
});

it('shows a slot not available error when the slot is already taken', function (): void {
    $this->actingAs($this->user);

    $selectedDate = $this->scheduleDay->format('Y-m-d');

    // First, create an appointment that blocks the first slot (10:00)
    $blockedStart = $this->scheduleDay->setTime(10, 0);

    Appointment::factory()->create([
        'doctor_id' => $this->doctor->id,
        'patient_id' => $this->patient->id,
        'start_time' => $blockedStart,
        'end_time' => $blockedStart->copy()->addMinutes(30),
        'state' => 'pending',
    ]);

    /** @var \Livewire\Testing\TestableLivewire $component */
    $component = Livewire::test(\App\Livewire\Patient\BookAppointment::class, [
        'doctor' => $this->doctor,
    ]);

    $component->set('selectedDate', $selectedDate);
    $component->call('loadSlots');

    // The 10:00 slot should NOT be in available slots
    $slots = $component->get('availableSlots');
    $blockedIso = $blockedStart->setTimezone('UTC')->toImmutable()->toIso8601String();

    $hasBlockedSlot = collect($slots)->contains(
        fn (array $slot) => $slot['start']->toIso8601String() === $blockedIso
    );

    expect($hasBlockedSlot)->toBeFalse(
        'The already-booked slot must not appear in available slots.'
    );

    // Try to book the blocked slot anyway (simulating a stale page)
    $component->set('selectedSlot', $blockedIso);
    $component->call('book');

    // Must show an error — slot no longer available
    $component->assertHasErrors('selectedSlot');
});
