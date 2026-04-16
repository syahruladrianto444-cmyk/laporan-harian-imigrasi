<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · Sistem Laporan Harian Imigrasi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'gov-blue': '#0B3C8C',
                        'gov-blue-light': '#1E88E5',
                        'gov-blue-pale': '#EEF3FB',
                        'gov-blue-mid': '#1A5CB8',
                        'gov-accent': '#FFD700',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        .login-bg {
            background: linear-gradient(135deg, #0B3C8C 0%, #1A5CB8 40%, #1E88E5 100%);
        }
        .glass-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body class="login-bg font-sans min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Logo / Header -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 mx-auto bg-white/20 rounded-full flex items-center justify-center border-2 border-white/30 backdrop-blur-sm mb-4">
                <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <h1 class="text-white text-2xl font-bold tracking-wide">SISTEM LAPORAN HARIAN</h1>
            <p class="text-blue-200 text-sm font-medium tracking-wider mt-1">KANTOR IMIGRASI</p>
        </div>

        <!-- Login Card -->
        <div class="glass-card rounded-2xl p-8">
            <h2 class="text-center text-gov-blue text-lg font-bold mb-1">Masuk ke Sistem</h2>
            <p class="text-center text-slate-400 text-xs mb-6">Silakan masukkan kredensial Anda</p>

            @if($errors->any())
            <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wider">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            placeholder="admin@imigrasi.go.id"
                            class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gov-blue-light focus:border-transparent transition-all"
                        >
                    </div>
                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-600 mb-1.5 uppercase tracking-wider">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            placeholder="••••••••"
                            class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-gov-blue-light focus:border-transparent transition-all"
                        >
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="remember" id="remember" class="rounded border-slate-300 text-gov-blue focus:ring-gov-blue-light">
                        <label for="remember" class="ml-2 text-xs text-slate-500">Ingat saya</label>
                    </div>
                    <button
                        type="submit"
                        class="w-full py-3 text-sm font-bold text-white rounded-xl shadow-lg transition-all hover:shadow-xl hover:-translate-y-0.5 active:translate-y-0"
                        style="background: linear-gradient(135deg, #0B3C8C, #1E88E5);"
                    >
                        MASUK
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-blue-200/60 text-xs mt-6">&copy; {{ date('Y') }} Direktorat Jenderal Imigrasi</p>
    </div>

</body>
</html>
