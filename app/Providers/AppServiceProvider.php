<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\DoctorScheduleOverride;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use App\Policies\DoctorScheduleOverridePolicy;
use App\Policies\DoctorSchedulePolicy;
use App\Policies\MedicalHistoryPolicy;
use App\Policies\PatientPolicy;
use App\Policies\PrescriptionPolicy;
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
        Gate::define('manage-any', function (User $user): bool {
            return $user->isAdmin() || $user->hasRole('admin');
        });

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Patient::class, PatientPolicy::class);
        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(MedicalHistory::class, MedicalHistoryPolicy::class);
        Gate::policy(DoctorSchedule::class, DoctorSchedulePolicy::class);
        Gate::policy(DoctorScheduleOverride::class, DoctorScheduleOverridePolicy::class);
        Gate::policy(Prescription::class, PrescriptionPolicy::class);
    }
}
