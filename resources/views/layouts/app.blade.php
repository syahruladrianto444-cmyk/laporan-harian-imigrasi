<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem Otomasi Laporan Harian Imigrasi">
    <title>@yield('title', 'Sistem Laporan Harian Imigrasi')</title>

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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #1E88E5; border-radius: 3px; }

        .header-gradient {
            background: linear-gradient(135deg, #0B3C8C 0%, #1A5CB8 50%, #1E88E5 100%);
        }

        .sidebar-link {
            transition: all 0.2s ease;
        }
        .sidebar-link:hover {
            background: rgba(30,136,229,0.08);
        }
        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(11,60,140,0.10) 0%, rgba(30,136,229,0.12) 100%);
            border-right: 3px solid #1E88E5;
            color: #0B3C8C;
            font-weight: 600;
        }

        .upload-zone {
            background: linear-gradient(135deg, rgba(11,60,140,0.04) 0%, rgba(30,136,229,0.06) 100%);
            transition: all 0.3s ease;
        }
        .upload-zone:hover, .upload-zone.dragover {
            background: linear-gradient(135deg, rgba(11,60,140,0.10) 0%, rgba(30,136,229,0.12) 100%);
            border-color: #1E88E5;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(30,136,229,0.15);
        }

        tbody tr { transition: background-color 0.15s ease; }
        tbody tr:hover { background-color: #EEF3FB !important; }

        .btn-primary {
            background: linear-gradient(135deg, #0B3C8C, #1E88E5);
            transition: all 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(30,136,229,0.40);
        }
        .btn-primary:active { transform: translateY(0); }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-animate { animation: slideDown 0.4s ease forwards; }

        @keyframes progress {
            from { width: 0%; }
            to   { width: 100%; }
        }
        .progress-bar { animation: progress 2s ease-in-out forwards; }

        .stat-card {
            background: white;
            border-left: 4px solid #1E88E5;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(11,60,140,0.12);
        }

        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .file-tag {
            background: linear-gradient(135deg, #EEF3FB, #dbe8fa);
            border: 1px solid #b3d1f5;
        }

        @yield('extra-styles')
    </style>
</head>
<body class="bg-slate-50 font-sans min-h-screen" x-data="{ sidebarOpen: false }">

    <!-- ===== TOP HEADER ===== -->
    <header class="header-gradient text-white shadow-2xl fixed top-0 left-0 right-0 z-50 h-16">
        <div class="h-full px-4 lg:px-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Mobile menu button -->
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-1.5 rounded-lg hover:bg-white/10 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm border border-white/30">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="hidden sm:block">
                    <h1 class="text-base font-bold tracking-wide leading-tight">SISTEM LAPORAN HARIAN IMIGRASI</h1>
                    <p class="text-blue-200 text-[10px] font-medium tracking-widest uppercase leading-relaxed">Kementerian Imigrasi dan Pemasyarakatan Republik Indonesia · Direktorat Jenderal Imigrasi</p>
                    <p class="text-blue-200 text-[10px] font-medium tracking-widest uppercase leading-relaxed">Kantor Wilayah Jawa Tengah · Kantor Imigrasi Kelas I Non TPI Pemalang</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center gap-3 text-right">
                    <div>
                        <p class="text-blue-200 text-[10px]">Tanggal</p>
                        <p class="text-xs font-semibold">{{ now()->translatedFormat('d M Y') }}</p>
                    </div>
                    <div class="w-px h-8 bg-white/30"></div>
                    <div>
                        <p class="text-blue-200 text-[10px]">User</p>
                        <p class="text-xs font-semibold">{{ Auth::user()->name ?? 'Admin' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="p-2 rounded-lg hover:bg-white/10 transition-colors" title="Logout">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- ===== SIDEBAR ===== -->
    <!-- Overlay -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         x-cloak
         class="fixed inset-0 bg-black/40 z-40 lg:hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    <aside class="fixed top-16 left-0 bottom-0 w-60 bg-white border-r border-slate-200 z-40 transform transition-transform duration-200 ease-in-out lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="h-full flex flex-col">
            <div class="p-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Menu Utama</p>
                <nav class="space-y-1">
                    <a href="{{ route('pdf.index') }}"
                       class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-600 {{ request()->routeIs('pdf.*') || request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <div>
                            <span class="block leading-tight">Laporan Izin Tinggal</span>
                            <span class="text-[10px] text-slate-400 font-normal">Deteksi PDF</span>
                        </div>
                    </a>
                    {{-- Laporan ABK Ganda (hidden) --}}

                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-4 mb-2">Dokumen Tambahan</p>

                    <a href="{{ route('avidavit.index') }}"
                       class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-600 {{ request()->routeIs('avidavit.*') ? 'active' : '' }}">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div>
                            <span class="block leading-tight">Laporan Avidavit</span>
                            <span class="text-[10px] text-slate-400 font-normal">Deteksi PDF</span>
                        </div>
                    </a>
                    <a href="{{ route('skim.index') }}"
                       class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-600 {{ request()->routeIs('skim.*') ? 'active' : '' }}">
                        <svg class="w-5 h-5 text-teal-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div>
                            <span class="block leading-tight">Laporan SKIM</span>
                            <span class="text-[10px] text-slate-400 font-normal">Deteksi PDF</span>
                        </div>
                    </a>
                    <a href="{{ route('abg.index') }}"
                       class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-slate-600 {{ request()->routeIs('abg.*') ? 'active' : '' }}">
                        <svg class="w-5 h-5 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div>
                            <span class="block leading-tight">Laporan ABG</span>
                            <span class="text-[10px] text-slate-400 font-normal">Deteksi PDF</span>
                        </div>
                    </a>
                </nav>
            </div>

            <!-- Sidebar footer -->
            <div class="mt-auto p-4 border-t border-slate-100">
                <div class="bg-gov-blue-pale rounded-xl p-3">
                    <p class="text-[10px] font-bold text-gov-blue uppercase tracking-wider">Panduan Singkat</p>
                    <p class="text-[10px] text-slate-500 mt-1 leading-relaxed">
                        Upload file PDF, sistem akan otomatis mengekstrak data dan menyimpannya ke database. Gunakan tombol Export untuk mengunduh laporan Excel.
                    </p>
                </div>
            </div>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="lg:ml-60 pt-16 min-h-screen">
        <div class="max-w-screen-2xl mx-auto px-4 lg:px-6 py-6 space-y-6">
            @yield('content')
        </div>
    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="lg:ml-60 py-4 text-center text-xs text-slate-400 border-t border-slate-200">
        <p>Sistem Otomasi Laporan Harian Imigrasi &copy; {{ date('Y') }} · Direktorat Jenderal Imigrasi</p>
    </footer>

    @yield('scripts')

</body>
</html>
