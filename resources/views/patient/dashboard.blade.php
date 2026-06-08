<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ auth()->user()->name }}</h1>

    @if($appointments->count() > 0)
        <div class="space-y-4">
            @foreach($appointments as $appointment)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $appointment->doctor->user->name }}</p>
                            <p class="text-sm text-gray-600">{{ $appointment->doctor->specialty->name }}</p>
                            <p class="text-sm text-gray-500">
                                {{ $appointment->start_time->format('M d, Y') }} at {{ $appointment->start_time->format('H:i') }}
                            </p>
                        </div>
                        <span class="px-2 py-1 text-xs font-medium rounded-full
                            @if($appointment->state === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($appointment->state === 'confirmed') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800
                            @endif"
                        >
                            {{ $appointment->state }}
                        </span>
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
