<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clinic Timezone
    |--------------------------------------------------------------------------
    |
    | The IANA timezone the clinic operates in. Used as the default timezone
    | for API responses (the per-request override is `?tz=`) and as the
    | consultorio timezone for any future display logic.
    |
    | Override per-environment via the CLINIC_TIMEZONE env var in .env.
    |
    */

    'timezone' => env('CLINIC_TIMEZONE', 'America/Argentina/Buenos_Aires'),

];
