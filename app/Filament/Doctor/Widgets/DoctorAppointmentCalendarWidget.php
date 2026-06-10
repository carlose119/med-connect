<?php

namespace App\Filament\Doctor\Widgets;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Collection;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class DoctorAppointmentCalendarWidget extends FullCalendarWidget
{
    protected static ?string $slug = 'doctor-appointment-calendar';

    protected function heading(): string
    {
        return 'My Appointments';
    }

    protected function headerActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function viewAction(): Action
    {
        return Action::make('view')
            ->label('View Details')
            ->modalHeading('Appointment Details')
            ->form($this->getViewFormSchema())
            ->modalWidth('lg');
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $doctor = auth()->user()->doctor;

        if (! $doctor) {
            return [];
        }

        $start = Carbon::parse($fetchInfo['start'])->startOfDay();
        $end = Carbon::parse($fetchInfo['end'])->endOfDay();

        $appointments = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->where('start_time', '>=', $start)
            ->where('start_time', '<=', $end)
            ->with(['patient.user'])
            ->get();

        return $appointments->map(fn (Appointment $apt): array => [
            'id' => $apt->id,
            'title' => $apt->patient->user->name,
            'start' => $apt->start_time->toIso8601String(),
            'end' => $apt->end_time->toIso8601String(),
            'color' => $this->getStatusColor($apt->state->value ?? 'pending'),
            'extendedProps' => [
                'state' => $apt->state->value ?? 'pending',
                'patient_phone' => $apt->patient->phone,
                'notes' => $apt->notes,
                'patient_id' => $apt->patient_id,
            ],
        ])->all();
    }

    public function getFormSchema(): array
    {
        $doctor = auth()->user()->doctor;

        return [
            Select::make('patient_id')
                ->label('Patient')
                ->searchable()
                ->preload()
                ->getSearchResultsUsing(fn (string $search): array => $this->searchPatients($search))
                ->getOptionLabelUsing(fn ($value): ?string => $this->getPatientLabel($value))
                ->required(),
            DateTimePicker::make('start_time')
                ->label('Start Time')
                ->required()
                ->seconds(false),
            DateTimePicker::make('end_time')
                ->label('End Time')
                ->required()
                ->seconds(false),
            Textarea::make('notes')
                ->label('Notes')
                ->rows(3),
        ];
    }

    protected function getViewFormSchema(): array
    {
        return [
            Select::make('state')
                ->label('Status')
                ->disabled()
                ->options($this->getStatusOptions()),
            DateTimePicker::make('start_time')
                ->label('Start Time')
                ->disabled(),
            DateTimePicker::make('end_time')
                ->label('End Time')
                ->disabled(),
            Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->disabled(),
        ];
    }

    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        $appointment = Appointment::find($event['id']);

        if (! $appointment) {
            return true;
        }

        $start = Carbon::parse($event['start']);
        $end = Carbon::parse($event['end']);
        $appointment->update([
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $appointment->update([
            'start_time' => $start,
            'end_time' => $end,
        ]);

        $this->refreshRecords();

        return false;
    }

    protected function getStatusColor(string $state): string
    {
        return match ($state) {
            'pending' => '#f59e0b',
            'confirmed' => '#3b82f6',
            'completed' => '#10b981',
            'cancelled' => '#ef4444',
            'no_show' => '#6b7280',
            default => '#94a3b8',
        };
    }

    protected function getStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
        ];
    }

    protected function searchPatients(string $search): array
    {
        return \App\Models\Patient::query()
            ->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orWhere('identification_number', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->limit(20)
            ->pluck('user.name', 'id')
            ->all();
    }

    protected function getPatientLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $patient = \App\Models\Patient::with('user')->find($value);

        return $patient?->user->name;
    }
}