<?php

namespace App\Livewire\Patient;

use App\Models\Doctor;
use App\Models\Specialty;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.patient')]
#[Title('Doctors')]
class DoctorList extends Component
{
    #[Url(as: 'specialty')]
    public ?string $specialty = null;

    public function render(): View
    {
        $doctors = Doctor::with(['user', 'specialty'])
            ->when($this->specialty, fn ($q) => $q->whereHas('specialty', fn ($q) => $q->where('name', $this->specialty)))
            ->get();

        $specialties = Specialty::whereHas('doctors')->get();

        return view('patient.doctors', [
            'doctors' => $doctors,
            'specialties' => $specialties,
        ]);
    }
}
