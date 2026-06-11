<?php

namespace App\Filament\Doctor\Pages;

use App\Models\Patient;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Walk-in patient registration page for the doctor panel.
 *
 * Allows a doctor to register a new patient (User + Patient in one
 * DB transaction) and immediately proceed to a walk-in consultation.
 */
class RegisterWalkInPatient extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Paciente sin Cita';

    protected static ?string $title = 'Registrar Paciente sin Cita';

    protected static ?string $slug = 'register-walk-in';

    protected string $view = 'filament-panels::pages.simple';

    /** @var array<string, mixed> */
    public array $formData = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Fieldset::make('Datos del Paciente')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre completo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('identification_number')
                            ->label('Número de identificación')
                            ->maxLength(50),
                        DatePicker::make('birth_date')
                            ->label('Fecha de nacimiento')
                            ->maxDate(now()),
                        Radio::make('gender')
                            ->label('Género')
                            ->options([
                                'male' => 'Masculino',
                                'female' => 'Femenino',
                                'other' => 'Otro',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        $formSchema = $this->form($schema);

        return $schema
            ->columns(2)
            ->components([
                Form::make()
                    ->statePath('formData')
                    ->livewireSubmitHandler('register')
                    ->schema($formSchema->getComponents())
                    ->footer([
                        Actions::make([
                            Action::make('register')
                                ->label('Registrar y Continuar a Consulta')
                                ->color('primary')
                                ->submit('register'),
                        ]),
                    ]),
            ]);
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function register(): void
    {
        $data = $this->formData;

        if (empty($data['name'])) {
            Notification::make()
                ->title('Campo requerido')
                ->body('El nombre es obligatorio.')
                ->danger()
                ->send();

            return;
        }

        if (empty($data['gender'])) {
            Notification::make()
                ->title('Campo requerido')
                ->body('Debe seleccionar un género.')
                ->danger()
                ->send();

            return;
        }

        if (! empty($data['email']) && User::where('email', $data['email'])->exists()) {
            Notification::make()
                ->title('El correo ya está registrado')
                ->body('Este correo electrónico ya pertenece a otro usuario.')
                ->danger()
                ->send();

            return;
        }

        try {
            /** @var Patient $patient */
            $patient = DB::transaction(function () use ($data): Patient {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'] ?? null,
                    'password' => bcrypt(bin2hex(random_bytes(8))),
                    'role' => 'patient',
                    'is_active' => true,
                ]);

                return Patient::create([
                    'user_id' => $user->id,
                    'identification_number' => $data['identification_number'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'birth_date' => $data['birth_date'] ?? null,
                    'gender' => $data['gender'],
                ]);
            });

            Notification::make()
                ->title('Paciente registrado')
                ->body("Paciente {$patient->user->name} registrado correctamente.")
                ->success()
                ->send();

            $this->redirect(route('doctor.consultation', ['patient_id' => $patient->id]), navigate: true);

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Error al registrar')
                ->body('Ocurrió un error al registrar el paciente. Intente nuevamente.')
                ->danger()
                ->send();
        }
    }
}