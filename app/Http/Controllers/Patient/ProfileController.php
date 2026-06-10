<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('patient.profile', [
            'user' => Auth::user(),
            'patient' => Auth::user()->patient,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $patient = $user->patient;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'identification_number' => ['required', 'string', 'max:50', 'unique:patients,identification_number,'.$patient->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        $patient->update([
            'identification_number' => $validated['identification_number'],
            'phone' => $validated['phone'] ?? $patient->phone,
            'birth_date' => $validated['birth_date'] ?? $patient->birth_date,
            'gender' => $validated['gender'] ?? $patient->gender,
        ]);

        return redirect(route('patient.profile'))->with('status', 'Profile updated successfully.');
    }
}
