<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @hasSection('title')
            @yield('title') - {{ config('app.name') }}
        @else
            Manajemen Semester - {{ config('app.name') }}
        @endif
    </title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 text-gray-900">
<div class="min-h-screen">
    <nav class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-xl font-semibold text-gray-800">
                @yield('page-title', 'Semester & Jadwal Default')
            </h1>
            <a href="{{ route('admin.home') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Kembali ke Dashboard</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @if (session('status'))
            <div class="mb-4 rounded border border-green-300 bg-green-50 px-4 py-3 text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('payload'))
            <div class="mb-4 rounded border border-red-300 bg-red-50 px-4 py-3 text-red-700">
                {{ $errors->first('payload') }}
            </div>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>