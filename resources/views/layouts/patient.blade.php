<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? 'Patient Portal') - MedConnect</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-8">
                    <a href="{{ route('patient.dashboard') }}"
                        class="text-xl font-bold text-blue-600 hover:text-blue-700 transition-colors">
                        MedConnect
                    </a>

                    @auth
                        <div class="hidden md:flex items-center space-x-1">
                            <a href="{{ route('patient.dashboard') }}"
                                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('patient.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                </svg>
                                Dashboard
                            </a>

                            <a href="{{ route('patient.doctors') }}"
                                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('patient.doctors', 'patient.book') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                Buscar Doctor
                            </a>

                            <a href="{{ route('patient.profile') }}"
                                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('patient.profile') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Mi Perfil
                            </a>
                        </div>
                    @endauth
                </div>

                <div class="flex items-center space-x-4">
                    @auth
                        <div class="hidden md:flex items-center gap-3">
                            <div class="flex items-center gap-2 text-sm text-gray-600">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <span
                                        class="text-xs font-bold text-blue-600">{{ substr(auth()->user()->name, 0, 1) }}</span>
                                </div>
                                <span class="font-medium">{{ auth()->user()->name }}</span>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('patient.logout') }}" class="inline">
                            @csrf
                            <button type="submit"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Cerrar sesión
                            </button>
                        </form>
                    @endauth

                    {{-- Mobile menu toggle --}}
                    <button id="mobile-menu-toggle" type="button"
                        class="md:hidden p-2 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                        onclick="document.getElementById('mobile-menu').classList.toggle('hidden')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div id="mobile-menu" class="hidden md:hidden border-t border-gray-100">
            <div class="px-4 py-3 space-y-1">
                <a href="{{ route('patient.dashboard') }}"
                    class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('patient.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' }}">
                    Dashboard
                </a>
                <a href="{{ route('patient.doctors') }}"
                    class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('patient.doctors', 'patient.book') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' }}">
                    Buscar Doctor
                </a>
                <a href="{{ route('patient.profile') }}"
                    class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium {{ request()->routeIs('patient.profile') ? 'bg-blue-50 text-blue-700' : 'text-gray-600 hover:bg-gray-50' }}">
                    Mi Perfil
                </a>
            </div>
        </div>
    </nav>

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full">
        @if (session('status'))
            <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-md text-sm">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-md text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="bg-white border-t border-gray-200 py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-sm text-gray-400">
            &copy; {{ date('Y') }} MedConnect. All rights reserved.
        </div>
    </footer>
</body>

</html>
