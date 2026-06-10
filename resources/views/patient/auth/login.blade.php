@extends('layouts.patient')

@section('title', 'Login')

@section('content')
<div class="max-w-md mx-auto mt-10">
    <h1 class="text-2xl font-bold text-gray-900 mb-6 text-center">Patient Login</h1>

    <form method="POST" action="{{ route('patient.login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" id="password" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center">
            <input type="checkbox" name="remember" id="remember" value="1"
                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
        </div>

        <div>
            <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Login
            </button>
        </div>
    </form>

    <p class="mt-4 text-center text-sm text-gray-600">
        Don't have an account?
        <a href="{{ route('patient.register') }}" class="text-blue-600 hover:text-blue-800">Register</a>
    </p>
</div>
@endsection
