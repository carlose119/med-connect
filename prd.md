## **Proyecto: Sistema Integral de Gestión Médica (MedConnect)** 

## **1. Visión General y Alcance** 

El sistema consiste en una plataforma ecosistémica diseñada para digitalizar la gestión de consultas, agendas médicas e historiales clínicos. Centraliza la operación administrativa y el flujo de trabajo de los médicos en una plataforma web, mientras despliega un canal accesible (Web + App Móvil) para que los pacientes gestionen su experiencia médica de forma autónoma. 

## **Componentes del Ecosistema** 

- **Backend & Panel de Administración (Laravel 13 + FilamentPHP v5):** Núcleo del sistema, encargado de la API, la lógica de negocio y los paneles para Administradores y Médicos. 

- **Frontend Público (Web Pacientes):** Portal ligero para el registro, consulta y reserva de citas en línea. 

- **Aplicación Móvil (React Native Expo):** Orientada exclusivamente a pacientes, consumiendo la API de Laravel para ofrecer una experiencia nativa de reserva y consulta. 

## **2. Arquitectura Tecnológica y Stack** 

- **Backend:** Laravel 13 (PHP 8.4+) 

- **Panel Administrativo/Médico:** FilamentPHP v5 (Livewire, Alpine.js, Tailwind CSS) 

- **Autenticación de la API:** Laravel Sanctum (Tokens SPA y móviles) 

- **Base de Datos:** PostgreSQL o MySQL 

- **Frontend Web Pacientes:** Blade + Tailwind CSS (o framework JS de preferencia, consumiendo la API) 

- **App Móvil:** React Native con Expo (Gestionado con Expo Router, Axios/TanStack Query para peticiones) 

## **3. Gestión de Roles y Permisos (RBAC)** 

El sistema implementará un control de acceso basado en roles mediante las políticas nativas de Laravel y Filament: 

|**Rol**|**Canal de**<br>**Acceso**|**Permisos Clave**|
|---|---|---|
|**Administrador**|Panel<br>Filament|Control total del sistema, creación de cuentas<br>médicas, configuración global, auditoría de|



|**Rol**|**Canal de**<br>**Acceso**|**Permisos Clave**|
|---|---|---|
||Web|registros.|
|**Médico**|Panel<br>Filament<br>Web|Gestión de agenda propia, visualización de<br>pacientes asignados, edición/creación de<br>historiales clínicos y emisión de récipes.|
|**Paciente**|Web<br>Pública / App<br>Expo|Registro autónomo, gestión de perfil, reserva de<br>citas (gratuito/presencial), visualización de su<br>propio historial y récipes.|



## **4. Requisitos Funcionales por Módulo** 

## **Módulo 1: Gestión de Usuarios y Autenticación** 

- **RF-1.1:** El administrador puede crear, editar y suspender usuarios tipo **Médico** desde el panel de Filament. 

- **RF-1.2:** Los médicos reciben un correo automatizado para activar su cuenta y definir su contraseña. 

- **RF-1.3:** Los pacientes pueden registrarse autónomamente desde la Web o la App Móvil con validación de campos obligatorios (Cédula/DNI, Nombre, Email, Teléfono). 

- **RF-1.4:** Autenticación segura mediante Laravel Sanctum para la App Móvil (mantenimiento de sesión mediante tokens). 

## **Módulo 2: Agenda y Gestión de Citas** 

- **RF-2.1: Configuración de Horarios:** Cada médico puede definir sus bloques de disponibilidad horaria (ej. Lunes de 08:00 a 12:00) y la duración estándar de la cita desde su panel. 

- **RF-2.2: Reserva de Citas (Paciente):** El paciente selecciona especialidad/médico, visualiza los días y horas disponibles en tiempo real y reserva sin requerir pasarela de pago (modalidad presencial/gratuito). 

- **RF-2.3: Flujo de Estados:** Las citas transitarán por los estados: `Pendiente` , `Confirmada` , `Completada` o `Cancelada` . 

- **RF-2.4: Vista de Agenda (Médico):** El médico contará con una vista de calendario (Filament FullCalendar Plugin) para visualizar sus compromisos diarios y semanales. 

## **Módulo 3: Historial Clínico Digital (EHR)** 

- **RF-3.1: Creación Automática/Manual:** Al agendar por primera vez, el sistema genera el registro del historial clínico. Si el paciente es registrado directamente por el médico en consulta, este puede crear el historial desde cero. 

- **RF-3.2: Línea de Tiempo Clínico:** Cada consulta genera una "Nota de Evolución" (Motivo de consulta, examen físico, diagnóstico, observaciones). Estas notas se organizan cronológicamente. 

- **RF-3.3: Archivos Adjuntos:** El médico puede subir exámenes de laboratorio o imágenes médicas (PDF, PNG, JPG) directamente al historial del paciente usando los componentes de carga de archivos de Filament. 

- **RF-3.4: Restricción de Acceso:** Los pacientes **solo pueden leer** su historial desde la App o Web; no pueden alterarlo bajo ningún concepto. 

## **Módulo 4: Récipes / Recetas Médicas** 

- **RF-4.1: Emisión Digital:** Durante la consulta, el médico dispone de un formulario repetidor (Filament Repeater) para añadir medicamentos (Nombre, Dosis, Frecuencia, Duración). 

- **RF-4.2: Identificador Único:** Cada récipe se genera con un ID único/código de barras referencial para evitar duplicidades. 

- **RF-4.3: Visualización Móvil:** El paciente puede abrir la sección "Mis Récipes" en la App Expo, ver las indicaciones detalladas y descargar la receta en formato PDF optimizado para dispositivos móviles. 

## **5. Propuesta de Modelo de Datos (Esquema de Base de Datos)** 

```
[users]
  - id (PK)
  - name
  - email
  - password
  - role (admin, doctor, patient)
[doctors]
  - id (PK)
  - user_id (FK)
  - specialty
  - schedule_settings (JSON para días/horas hábiles)
[patients]
  - id (PK)
  - user_id (FK)
  - identification_number (DNI/Cédula)
  - phone
  - birth_date
  - gender
[appointments]
  - id (PK)
  - doctor_id (FK)
  - patient_id (FK)
  - appointment_date (DateTime)
```

```
  - status (pending, confirmed, completed, cancelled)
  - notes
[medical_histories]
  - id (PK)
  - patient_id (FK)
  - created_at
[medical_notes] (Línea de tiempo del historial)
  - id (PK)
  - medical_history_id (FK)
  - doctor_id (FK)
  - symptoms
  - diagnosis
  - treatment_notes
  - attachments (JSON / file paths)
  - created_at
[prescriptions]
  - id (PK)
  - appointment_id (FK)
  - doctor_id (FK)
  - patient_id (FK)
  - medicines (JSON: [{name, dosage, frequency, period}])
  - unique_code
  - created_at
```

## **6. Mapa de Endpoints de la API (Para React Native Expo)** 

Todos los endpoints (excepto registro y login públicos) requerirán el header `Authorization: Bearer {token}` . 

## **Autenticación y Perfil** 

- `POST /api/v1/register` -> Registro del paciente. 

- `POST /api/v1/login` -> Autenticación, retorna el token Sanctum. 

- `GET /api/v1/profile` -> Obtiene los datos del paciente autenticado. 

## **Médicos y Disponibilidad** 

- `GET /api/v1/doctors` -> Listado de médicos disponibles y especialidades. 

- `GET /api/v1/doctors/{id}/availability` -> Retorna los bloques horarios libres de un médico para una fecha específica. 

## **Citas Médicas** 

- `GET /api/v1/appointments` -> Listado del historial de citas del paciente (Próximas y Pasadas). 

- `POST /api/v1/appointments` -> Reserva una nueva cita (Parámetros: `doctor_id` , `date` , `notes` ). 

- `PATCH /api/v1/appointments/{id}/cancel` -> Cancela una cita por parte del paciente. 

## **Historial Clínico y Récipes** 

- `GET /api/v1/medical-history` -> Retorna la línea de tiempo del historial clínico del paciente logueado. 

- `GET /api/v1/prescriptions` -> Listado de recetas emitidas al paciente. 

- `GET /api/v1/prescriptions/{id}/pdf` -> Descarga o visualización del PDF de la receta. 

## **7. Requisitos No Funcionales y Seguridad** 

- **Seguridad de los Datos:** Encriptación de datos sensibles en tránsito (HTTPS obligatorio en todas las solicitudes de la app móvil y paneles web). 

- **Estrategia de Desarrollo en Paralelo:** El backend debe priorizar el desarrollo de las migraciones y las Factorías ( `Factories` ) de datos. Con esto, el equipo de frontend/móvil puede trabajar con datos simulados ( _mocking_ ) usando herramientas como MSW o los mismos controladores de Laravel devolviendo estructuras estáticas antes de conectar la base de datos real. 

- **Diseño Responsivo e Interfaz:** El front público de pacientes debe ser _mobilefirst_ , asegurando que la experiencia web se acople a la perfección con la aplicación nativa en Expo. 

