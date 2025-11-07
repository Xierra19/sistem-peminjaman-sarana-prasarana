<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @hasSection('title')
            @yield('title') - {{ config('app.name') }}
        @else
            {{ config('app.name') }}
        @endif
    </title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
<div class="min-h-screen flex flex-col">
    <!-- Top Navbar (match AuthenticatedLayout.vue style) -->
    <nav class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="text-lg font-semibold text-gray-800">{{ config('app.name') }}</a>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-600">{{ auth()->user()->name ?? 'User' }}</span>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex flex-1">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r border-gray-200 p-4 space-y-2 hidden md:block">
            <h2 class="text-sm font-semibold text-gray-600 mb-2">Menu</h2>

            <nav class="space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-blue-50 text-blue-700 border border-blue-100 shadow-sm' : 'text-gray-700 hover:bg-gray-100' }}">Dashboard</a>

                @if (auth()->user()?->role === 'admin')
                    <div class="mt-2">
                        <div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Master Data</div>
                        <div class="ml-2 space-y-1">
                            <a href="{{ route('admin.campus.index') }}" class="block rounded-md px-3 py-1.5 text-sm {{ request()->routeIs('admin.campus.*') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">Master Campus</a>
                            <a href="{{ route('admin.buildings.index') }}" class="block rounded-md px-3 py-1.5 text-sm {{ request()->routeIs('admin.buildings.*') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">Master Building</a>
                            <a href="{{ route('admin.rooms.index') }}" class="block rounded-md px-3 py-1.5 text-sm {{ request()->routeIs('admin.rooms.*') ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">Master Rooms</a>
                        </div>
                    </div>
                @endif

                <a href="{{ route('bookings.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('bookings.index') || request()->routeIs('bookings.create') ? 'bg-blue-50 text-blue-700 border border-blue-100 shadow-sm' : 'text-gray-700 hover:bg-gray-100' }}">Request Booking</a>

                @if (auth()->user()?->role === 'admin')
                    <a href="{{ route('admin.bookings.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.bookings.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 shadow-sm' : 'text-gray-700 hover:bg-gray-100' }}">Approval Booking</a>
                @endif

                <a href="{{ route('history.index') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('history.*') ? 'bg-blue-50 text-blue-700 border border-blue-100 shadow-sm' : 'text-gray-700 hover:bg-gray-100' }}">History</a>
            </nav>
        </aside>

        <!-- Main -->
        <main class="flex-1 p-6 space-y-6">
            <header>
                <h1 class="text-xl font-semibold text-gray-800">@yield('page-title', 'Semester & Jadwal Default')</h1>
            </header>

            @if (session('status'))
                <div class="rounded border border-green-300 bg-green-50 px-4 py-3 text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('success'))
                <div class="rounded border border-green-300 bg-green-50 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->has('payload'))
                <div class="rounded border border-red-300 bg-red-50 px-4 py-3 text-red-700">
                    {{ $errors->first('payload') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
