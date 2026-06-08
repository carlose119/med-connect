<?php

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Specialty;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists all doctors with name and specialty', function (): void {
    $patient = Patient::factory()->create();
    $user = $patient->user;

    $doctors = Doctor::factory(5)->create();

    expect($doctors)->toHaveCount(5);

    $response = $this->actingAs($user)->get('/patient/doctors');
    $response->assertOk();

    foreach ($doctors as $doctor) {
        $response->assertSee($doctor->user->name);
        $response->assertSee($doctor->specialty->name);
    }
});

it('filters doctors by specialty', function (): void {
    $patient = Patient::factory()->create();
    $user = $patient->user;

    $cardiology = Specialty::factory()->create(['name' => 'Cardiology']);
    $dermatology = Specialty::factory()->create(['name' => 'Dermatology']);

    $cardioDoctor = Doctor::factory()->create(['specialty_id' => $cardiology->id]);
    $dermaDoctor1 = Doctor::factory()->create(['specialty_id' => $dermatology->id]);
    $dermaDoctor2 = Doctor::factory()->create(['specialty_id' => $dermatology->id]);

    $response = $this->actingAs($user)->get('/patient/doctors?specialty=Cardiology');
    $response->assertOk();

    $response->assertSee($cardioDoctor->user->name);
    $response->assertDontSee($dermaDoctor1->user->name);
    $response->assertDontSee($dermaDoctor2->user->name);
});
