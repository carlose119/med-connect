<div class="space-y-6">
    <div>
        <a href="{{ route('patient.doctors') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Back to Doctors</a>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h1 class="text-2xl font-bold text-gray-900">Book Appointment</h1>

        <!-- Doctor Info -->
        <div class="mt-4 p-4 bg-gray-50 rounded-lg">
            <p class="font-semibold text-gray-900">{{ $doctor->user->name }}</p>
            <p class="text-sm text-gray-600">{{ $doctor->specialty->name }}</p>
            @if($doctor->bio)
                <p class="text-sm text-gray-500 mt-1">{{ $doctor->bio }}</p>
            @endif
        </div>

        <!-- Date Picker -->
        <div class="mt-6">
            <label for="selectedDate" class="block text-sm font-medium text-gray-700">Select a Date</label>
            <input type="date"
                   id="selectedDate"
                   wire:model.live="selectedDate"
                   wire:change="loadSlots"
                   min="{{ $minDate }}"
                   max="{{ $maxDate }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @error('selectedDate')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Available Slots -->
        @if($selectedDate && count($availableSlots) > 0)
            <div class="mt-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Available Slots</h2>
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                    @foreach($availableSlots as $slot)
                        <button type="button"
                                wire:click="$set('selectedSlot', '{{ $slot['start']->toIso8601String() }}')"
                                class="px-3 py-2 text-sm rounded-md border transition-colors text-center
                                {{ $selectedSlot === $slot['start']->toIso8601String()
                                    ? 'bg-blue-600 text-white border-blue-600'
                                    : 'bg-white text-gray-700 border-gray-300 hover:bg-blue-50 hover:border-blue-300' }}">
                            {{ $slot['start']->setTimezone(app()->make('config')->get('app.timezone'))->format('H:i') }}
                        </button>
                    @endforeach
                </div>
            </div>
        @elseif($selectedDate)
            <div class="mt-6 p-4 bg-yellow-50 rounded-lg">
                <p class="text-sm text-yellow-800">No available slots for this date.</p>
            </div>
        @endif

        @error('selectedSlot')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        <!-- Book Button -->
        @if($selectedSlot)
            <div class="mt-6">
                <button type="button"
                        wire:click="book"
                        wire:loading.attr="disabled"
                        class="w-full sm:w-auto px-6 py-3 bg-blue-600 text-white font-medium rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                    Confirm Booking
                </button>
                <p class="mt-2 text-sm text-gray-500">
                    You are about to book:
                    <strong>{{ \Carbon\CarbonImmutable::parse($selectedSlot)->setTimezone(app()->make('config')->get('app.timezone'))->format('M d, Y \a\t H:i') }}</strong>
                </p>
            </div>
        @endif
    </div>
</div>
