<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\MedicalHistoryPolicy;
use App\Policies\PatientPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(MedicalHistory::class, MedicalHistoryPolicy::class);
    }
}
