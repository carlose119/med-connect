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
    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->isPatient()) {
            abort(403, 'Solo pacientes pueden acceder a esta sección.');
        }

        if ($user->patient === null) {
            abort(403, 'Tu cuenta no tiene un perfil de paciente asociado.');
        }
    }

    public function render(): View
    {
        $patient = auth()->user()->patient;

        $upcomingAppointments = $patient
            ->appointments()
            ->with(['doctor.user', 'doctor.specialty'])
            ->whereIn('state', ['pending', 'confirmed'])
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->get();

        $pastAppointments = $patient
            ->appointments()
            ->with(['doctor.user', 'doctor.specialty'])
            ->whereIn('state', ['completed', 'cancelled', 'no_show'])
            ->where('start_time', '<', now())
            ->orderByDesc('start_time')
            ->limit(10)
            ->get();

        return view('patient.dashboard', [
            'upcomingAppointments' => $upcomingAppointments,
            'pastAppointments' => $pastAppointments,
        ]);
    }
}
