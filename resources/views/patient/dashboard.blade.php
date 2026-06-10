<div class="space-y-8">
    <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ auth()->user()->name }}</h1>

    @if(session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('status') }}
        </div>
    @endif

    @if(session('errors'))
        @foreach(session('errors')->all() as $error)
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                {{ $error }}
            </div>
        @endforeach
    @endif

    <!-- Upcoming Appointments -->
    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Upcoming Appointments</h2>
        @if($upcomingAppointments->count() > 0)
            <div class="space-y-4">
                @foreach($upcomingAppointments as $appointment)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $appointment->doctor->user->name }}</p>
                                <p class="text-sm text-gray-600">{{ $appointment->doctor->specialty->name }}</p>
                                <p class="text-sm text-gray-500">
                                    {{ $appointment->start_time->format('M d, Y') }} at {{ $appointment->start_time->format('H:i') }}
                                </p>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    @if($appointment->state instanceof \App\States\Appointment\Pending) bg-yellow-100 text-yellow-800
                                    @elseif($appointment->state instanceof \App\States\Appointment\Confirmed) bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-800
                                    @endif"
                                >
                                    {{ $appointment->state }}
                                </span>
                                @if($appointment->state instanceof \App\States\Appointment\Pending || $appointment->state instanceof \App\States\Appointment\Confirmed)
                                    <form method="POST" action="{{ route('patient.cancel', $appointment) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                onclick="return confirm('Are you sure you want to cancel this appointment?')"
                                                class="px-2 py-1 text-xs font-medium text-red-600 bg-red-50 rounded-md hover:bg-red-100 transition-colors">
                                            Cancel
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <p class="text-gray-600">No upcoming appointments.</p>
            </div>
        @endif
    </div>

    <!-- Past Appointments -->
    @if($pastAppointments->count() > 0)
    <div>
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Past Appointments</h2>
        <div class="space-y-4">
            @foreach($pastAppointments as $appointment)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 opacity-75">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $appointment->doctor->user->name }}</p>
                            <p class="text-sm text-gray-600">{{ $appointment->doctor->specialty->name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $appointment->start_time->format('M d, Y') }} at {{ $appointment->start_time->format('H:i') }}
                            </p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                @if($appointment->state instanceof \App\States\Appointment\Completed) bg-blue-100 text-blue-800
                                @elseif($appointment->state instanceof \App\States\Appointment\Cancelled) bg-red-100 text-red-800
                                @elseif($appointment->state instanceof \App\States\Appointment\NoShow) bg-gray-100 text-gray-800
                                @endif"
                            >
                                {{ $appointment->state }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
