<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Consulta sin Cita - MedConnect Doctor</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <div class="flex flex-col min-h-screen">
        {{-- Nav --}}
        <nav class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center gap-8">
                        <a href="{{ route('filament.doctor.pages.dashboard') }}"
                            class="text-xl font-bold text-blue-600 hover:text-blue-700 transition-colors">
                            MedConnect Doctor
                        </a>
                    </div>
                    <div class="flex items-center space-x-4">
                        @auth
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <span class="text-xs font-bold text-indigo-600">{{ substr(auth()->user()->name, 0, 1) }}</span>
                                    </div>
                                    <span class="font-medium">{{ auth()->user()->name }}</span>
                                </div>
                            </div>
                            <a href="{{ route('filament.doctor.pages.dashboard') }}"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors">
                                Panel del Doctor
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </nav>

        {{-- Main Content --}}
        <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
            {{-- Alerts --}}
            @if (session('status'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-md text-sm">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Patient Info --}}
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Información del Paciente</h2>
                </div>
                <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Nombre</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $patient->user->name }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Número de Identificación</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $patient->identification_number ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Teléfono</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $patient->phone ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Email</label>
                        <p class="mt-1 text-sm text-gray-900">{{ $patient->user->email ?? '—' }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Estado de la Cita</label>
                        <p class="mt-1 text-sm font-semibold text-gray-900">{{ $walkInAppointment->state::class === 'App\States\Appointment\Pending' ? 'Pendiente' : ($walkInAppointment->state::class === 'App\States\Appointment\Confirmed' ? 'Confirmada' : ($walkInAppointment->state::class === 'App\States\Appointment\Completed' ? 'Completada' : $walkInAppointment->state::class)) }}</p>
                    </div>
                </div>
            </div>

            {{-- Clinical Note Form --}}
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Nota Clínica</h2>
                </div>
                <div class="px-6 py-4">
                    <form method="POST" action="{{ route('doctor.consultation.save-note', ['patient_id' => $patient->id]) }}">
                        @csrf
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <label for="symptoms" class="block text-sm font-medium text-gray-700 mb-1">Síntomas</label>
                                <textarea name="symptoms" id="symptoms" rows="4" placeholder="Describa los síntomas del paciente..."
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                            <div>
                                <label for="physical_exam" class="block text-sm font-medium text-gray-700 mb-1">Examen Físico</label>
                                <textarea name="physical_exam" id="physical_exam" rows="4" placeholder="Resultados del examen físico..."
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                            <div>
                                <label for="diagnosis" class="block text-sm font-medium text-gray-700 mb-1">Diagnóstico *</label>
                                <textarea name="diagnosis" id="diagnosis" rows="4" required placeholder="Diagnóstico del paciente..."
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                            <div>
                                <label for="treatment_notes" class="block text-sm font-medium text-gray-700 mb-1">Notas de Tratamiento</label>
                                <textarea name="treatment_notes" id="treatment_notes" rows="4" placeholder="Plan de tratamiento..."
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                        </div>

                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">

                        <div class="mt-6 flex gap-3">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                Guardar Nota Clínica
                            </button>

                            @if ($walkInAppointment->state::class === 'App\States\Appointment\Pending')
                                <form method="POST" action="{{ route('doctor.consultation.confirm', ['patient_id' => $patient->id]) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700">
                                        Confirmar Cita
                                    </button>
                                </form>
                            @endif

                            @if ($walkInAppointment->state::class === 'App\States\Appointment\Confirmed')
                                <form method="POST" action="{{ route('doctor.consultation.complete', ['patient_id' => $patient->id]) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-green-600 text-white hover:bg-green-700">
                                        Completar Consulta
                                    </button>
                                </form>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- Previous Notes --}}
            @if ($previousNotes->count() > 0)
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Notas Clínicas Anteriores</h2>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        @foreach ($previousNotes as $note)
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between text-xs text-gray-500 mb-3">
                                    <span>{{ $note->created_at->format('d/m/Y H:i') }}</span>
                                    <span>{{ $note->doctor?->user?->name ?? '—' }}</span>
                                </div>
                                @if ($note->symptoms)
                                    <p class="text-sm mb-2"><span class="font-medium text-gray-700">Síntomas:</span> {{ $note->symptoms }}</p>
                                @endif
                                @if ($note->diagnosis)
                                    <p class="text-sm mb-2"><span class="font-medium text-gray-700">Diagnóstico:</span> {{ $note->diagnosis }}</p>
                                @endif
                                @if ($note->physical_exam)
                                    <p class="text-sm mb-2"><span class="font-medium text-gray-700">Examen Físico:</span> {{ $note->physical_exam }}</p>
                                @endif
                                @if ($note->treatment_notes)
                                    <p class="text-sm"><span class="font-medium text-gray-700">Tratamiento:</span> {{ $note->treatment_notes }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Prescription --}}
            @if ($walkInAppointment->state::class === 'App\States\Appointment\Completed')
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Receta Médica</h2>
                    </div>
                    <div class="px-6 py-4">
                        <form method="POST" action="{{ route('doctor.consultation.prescription', ['patient_id' => $patient->id]) }}">
                            @csrf
                            <input type="hidden" name="patient_id" value="{{ $patient->id }}">

                            <div class="space-y-3 mb-4">
                                @foreach ($prescriptionItems as $index => $item)
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Medicamento</label>
                                            <input type="text" name="prescriptionItems[{{ $index }}][name]" placeholder="Nombre del medicamento"
                                                class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Dosis</label>
                                            <input type="text" name="prescriptionItems[{{ $index }}][dosage]" placeholder="Ej: 500mg"
                                                class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Frecuencia</label>
                                            <input type="text" name="prescriptionItems[{{ $index }}][frequency]" placeholder="Ej: Cada 8h"
                                                class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Duración</label>
                                            <input type="text" name="prescriptionItems[{{ $index }}][duration]" placeholder="Ej: 7 días"
                                                class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex gap-3">
                                <button type="button" onclick="addPrescriptionRow(this)"
                                    class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                    + Agregar medicamento
                                </button>
                            </div>

                            <div class="mt-4">
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md bg-amber-500 text-white hover:bg-amber-600">
                                    Emitir Receta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </main>

        {{-- Footer --}}
        <footer class="bg-white border-t border-gray-200 py-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-400">
                &copy; {{ date('Y') }} MedConnect. All rights reserved.
            </div>
        </footer>
    </div>

    <script>
        function addPrescriptionRow(button) {
            const container = button.parentElement.previousElementSibling;
            const index = container.querySelectorAll('.grid').length;
            const html = `<div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Medicamento</label>
                    <input type="text" name="prescriptionItems[${index}][name]" placeholder="Nombre del medicamento"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dosis</label>
                    <input type="text" name="prescriptionItems[${index}][dosage]" placeholder="Ej: 500mg"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Frecuencia</label>
                    <input type="text" name="prescriptionItems[${index}][frequency]" placeholder="Ej: Cada 8h"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Duración</label>
                    <input type="text" name="prescriptionItems[${index}][duration]" placeholder="Ej: 7 días"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </div>
            </div>`;
            container.insertAdjacentHTML('beforeend', html);
        }
    </script>
</body>
</html>