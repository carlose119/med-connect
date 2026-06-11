<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== USERS ===\n";
$users = DB::table('users')->limit(10)->get(['id','name','email','role']);
foreach ($users as $u) {
    echo "id={$u->id} name={$u->name} role={$u->role}\n";
}

echo "\n=== PATIENTS ===\n";
$patients = DB::table('patients')->limit(10)->get(['id','user_id','identification_number']);
foreach ($patients as $p) {
    echo "id={$p->id} user_id={$p->user_id} dni={$p->identification_number}\n";
}

echo "\n=== APPOINTMENTS (sample) ===\n";
$appointments = DB::table('appointments')->limit(5)->get(['id','patient_id','doctor_id','state']);
foreach ($appointments as $a) {
    echo "id={$a->id} patient_id={$a->patient_id} doctor_id={$a->doctor_id} state={$a->state}\n";
}