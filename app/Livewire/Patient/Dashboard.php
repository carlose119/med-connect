<?php

namespace App\Livewire\Patient;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.patient')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        $appointments = auth()->user()->patient
            ->appointments()
            ->with(['doctor.user', 'doctor.specialty'])
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->get();

        return view('patient.dashboard', [
            'appointments' => $appointments,
        ]);
    }
}
