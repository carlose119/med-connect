<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Our Doctors</h1>

    <!-- Specialty Filter -->
    <div class="flex gap-2">
        <a href="{{ route('patient.doctors') }}"
            class="px-3 py-1.5 text-sm rounded-md transition-colors
            {{ request('specialty') ? 'bg-gray-100 text-gray-600 hover:bg-gray-200' : 'bg-blue-600 text-white' }}">
            All
        </a>
        @foreach($specialties as $spec)
            <a href="{{ route('patient.doctors', ['specialty' => $spec->name]) }}"
                class="px-3 py-1.5 text-sm rounded-md transition-colors
                {{ request('specialty') === $spec->name ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                {{ $spec->name }}
            </a>
        @endforeach
    </div>

    <!-- Doctor Cards -->
    @if($doctors->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($doctors as $doctor)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="font-semibold text-gray-900">{{ $doctor->user->name }}</h3>
                    <p class="text-sm text-gray-600">{{ $doctor->specialty->name }}</p>
                    @if($doctor->bio)
                        <p class="text-sm text-gray-500 mt-2">{{ Str::limit($doctor->bio, 100) }}</p>
                    @endif
                    <div class="mt-3">
                        <a href="{{ route('patient.book', $doctor) }}"
                           class="inline-block px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors">
                            Book Appointment
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-gray-600">No doctors found for this specialty.</p>
        </div>
    @endif
</div>
