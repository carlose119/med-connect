@extends('layouts.patient')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ auth()->user()->name }}</h1>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <p class="text-gray-600">Your upcoming appointments will appear here.</p>
    </div>
</div>
@endsection
