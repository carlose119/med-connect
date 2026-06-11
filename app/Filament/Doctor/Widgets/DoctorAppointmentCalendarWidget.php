<?php

namespace App\Filament\Doctor\Widgets;

use App\Models\Appointment;
use App\Models\Patient;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

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

    public function onEventClick(array $event): void
    {
        $this->record = Appointment::with(['patient.user'])->find($event['id']);
        $this->mountAction('view');
    }

    protected function viewAction(): Action
    {
        return Action::make('view')
            ->label('Save Changes')
            ->modalHeading('Appointment Details')
            ->record($this->record)
            ->form($this->getViewFormSchema())
            ->modalWidth('lg')
            ->action(fn ($action) => $this->saveAppointmentFromView($action));
    }

    public function saveAppointmentFromView($action): void
    {
        $data = $action->getRawData();
        $record = $this->record;

        if (! $record) {
            return;
        }

        $record->state = $data['state'] ?? $record->state;
        $record->start_time = $data['start_time'] ?? $record->start_time;
        $record->end_time = $data['end_time'] ?? $record->end_time;
        $record->notes = $data['notes'] ?? $record->notes;
        $record->save();

        $this->refreshRecords();
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
            ->whereBetween('start_time', [$start, $end])
            ->with(['patient.user'])
            ->get();

        return $appointments->map(fn (Appointment $apt): array => [
            'id' => $apt->id,
            'title' => $apt->patient->user->name,
            'start' => $apt->start_time->toIso8601String(),
            'end' => $apt->end_time->toIso8601String(),
            'color' => $this->getStatusColor($apt->state ?? 'pending'),
            'extendedProps' => [
                'state' => $apt->state ?? 'pending',
                'patient_phone' => $apt->patient->phone,
                'notes' => $apt->notes,
                'patient_id' => $apt->patient_id,
            ],
        ])->all();
    }

    public function getFormSchema(): array
    {
        return [
            Select::make('patient_id')
                ->label('Patient')
                ->searchable()
                ->preload()
                ->getSearchResultsUsing(fn (string $search): array => self::searchPatients($search))
                ->getOptionLabelUsing(fn ($value): ?string => self::getPatientLabel($value))
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
            TextInput::make('patient_name')
                ->label('Patient')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(fn ($record) => $record?->patient?->user?->name ?? '—'),
            TextInput::make('patient_phone')
                ->label('Phone')
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(fn ($record) => $record?->patient?->phone ?? '—'),
            Select::make('state')
                ->label('Status')
                ->options($this->getStatusOptions())
                ->formatStateUsing(fn ($record) => $record?->state ?? 'pending'),
            DateTimePicker::make('start_time')
                ->label('Start')
                ->seconds(false)
                ->formatStateUsing(fn ($record) => $record?->start_time),
            DateTimePicker::make('end_time')
                ->label('End')
                ->seconds(false)
                ->formatStateUsing(fn ($record) => $record?->end_time),
            Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->formatStateUsing(fn ($record) => $record?->notes ?? ''),
        ];
    }

    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        $appointment = Appointment::find($event['id']);

        if (! $appointment) {
            return true;
        }

        $appointment->update([
            'start_time' => Carbon::parse($event['start']),
            'end_time' => Carbon::parse($event['end']),
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

    private static function searchPatients(string $search): array
    {
        return Patient::query()
            ->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orWhere('identification_number', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->limit(20)
            ->get()
            ->mapWithKeys(fn ($patient): array => [$patient->id => $patient->user->name])
            ->all();
    }

    private static function getPatientLabel(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return Patient::with('user')->find($value)?->user->name;
    }
}