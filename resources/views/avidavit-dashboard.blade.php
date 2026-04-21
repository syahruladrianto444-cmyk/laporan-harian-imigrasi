@extends('layouts.app')

@section('title', 'Laporan Avidavit · PDF & Word')

@section('content')

    <!-- ===== NAVIGATION TABS ===== -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <nav class="flex gap-0">
            <button onclick="showSection('upload')" id="tab-upload"
                class="tab-btn px-5 py-3.5 text-sm font-medium border-b-2 border-gov-blue-light text-gov-blue transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Upload Dokumen
            </button>
            <button onclick="showSection('data')" id="tab-data"
                class="tab-btn px-5 py-3.5 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-gov-blue transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Data Hasil Ekstraksi
                @if($totalCount > 0)
                <span class="bg-gov-blue text-white text-xs px-2 py-0.5 rounded-full">{{ $totalCount }}</span>
                @endif
            </button>
        </nav>
    </div>

    <!-- ===== ALERTS ===== -->
    @if(session('message'))
    <div class="alert-animate flex items-start gap-3 p-4 rounded-xl border
        {{ session('failed_count', 0) > 0 && session('success_count', 0) == 0 ? 'bg-red-50 border-red-200 text-red-800' : (session('failed_count', 0) > 0 ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800') }}">
        @if(session('failed_count', 0) > 0 && session('success_count', 0) == 0)
        <svg class="w-5 h-5 mt-0.5 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
        @elseif(session('failed_count', 0) > 0)
        <svg class="w-5 h-5 mt-0.5 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        @else
        <svg class="w-5 h-5 mt-0.5 shrink-0 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        @endif
        <div class="flex-1">
            <p class="font-semibold text-sm">{{ session('message') }}</p>
            @if(session('failed_details') && count(session('failed_details')) > 0)
            <ul class="mt-1 text-xs space-y-0.5 opacity-80">
                @foreach(session('failed_details') as $fail)
                <li>• <strong>{{ $fail['file'] }}</strong>: {{ $fail['error'] }}</li>
                @endforeach
            </ul>
            @endif
        </div>
    </div>
    @endif

    @if(session('deleted'))
    <div class="alert-animate flex items-center gap-3 p-4 rounded-xl bg-slate-100 border border-slate-200 text-slate-700">
        <svg class="w-5 h-5 text-slate-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        <p class="text-sm font-medium">{{ session('deleted') }}</p>
    </div>
    @endif

    <!-- ===== SECTION: UPLOAD ===== -->
    <section id="section-upload">
        <div x-data="uploadZone()" class="space-y-5">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-amber-50/60">
                    <h2 class="text-base font-semibold text-gov-blue flex items-center gap-2">
                        <svg class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Upload Dokumen Avidavit — Surat Keterangan
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Mendukung upload file PDF  (.docx). Maksimal 20MB/file</p>
                </div>
                <div class="p-6">
                    <form id="upload-form" action="{{ route('avidavit.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="upload-zone border-2 border-dashed border-amber-300/60 rounded-xl p-10 text-center cursor-pointer"
                            :class="{ 'dragover': isDragging }"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop($event)"
                            @click="$refs.fileInput.click()">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-16 h-16 rounded-full bg-amber-50 flex items-center justify-center" :class="{ 'bg-amber-100': isDragging }">
                                    <svg class="w-8 h-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-gov-blue font-semibold text-sm" x-text="isDragging ? 'Lepaskan file di sini...' : 'Klik atau Seret File PDF ke Sini'"></p>
                                    <p class="text-slate-400 text-xs mt-1">Surat Keterangan (Avidavit) · Kewarganegaraan</p>
                                </div>
                            </div>
                            <input type="file" name="docs[]" multiple accept=".pdf,.docx,.doc" x-ref="fileInput" class="hidden" @change="handleFileSelect($event)" id="avidavit-file-input">
                        </div>

                        <!-- File List Preview -->
                        <div x-show="files.length > 0" x-cloak class="mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold text-slate-600 uppercase tracking-wider">File Terpilih (<span x-text="files.length"></span>)</p>
                                <button type="button" @click="clearFiles()" class="text-xs text-red-400 hover:text-red-600 transition-colors">Hapus Semua</button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-48 overflow-y-auto pr-1">
                                <template x-for="(file, index) in files" :key="index">
                                    <div class="file-tag flex items-center gap-2 px-3 py-2 rounded-lg">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-medium text-amber-700 truncate" x-text="file.name"></p>
                                            <p class="text-xs text-slate-400" x-text="formatSize(file.size)"></p>
                                        </div>
                                        <button type="button" @click.stop="removeFile(index)" class="text-slate-400 hover:text-red-500 transition-colors shrink-0">
                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-5 flex items-center gap-3">
                            <button type="button" @click="submitForm()" :disabled="files.length === 0 || isUploading"
                                :class="files.length === 0 || isUploading ? 'opacity-50 cursor-not-allowed' : ''"
                                class="btn-primary flex items-center gap-2 px-6 py-2.5 text-white text-sm font-semibold rounded-xl shadow-md">
                                <template x-if="isUploading">
                                    <svg class="w-4 h-4 spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                </template>
                                <template x-if="!isUploading">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </template>
                                <span x-text="isUploading ? 'Memproses...' : 'Proses Dokumen'"></span>
                                <span x-show="files.length > 0 && !isUploading" x-cloak class="bg-white/20 text-xs px-1.5 py-0.5 rounded-full" x-text="files.length + ' file'"></span>
                            </button>
                            <p x-show="isUploading" x-cloak class="text-xs text-slate-500 animate-pulse">Mengekstraksi data, mohon tunggu...</p>
                        </div>
                        <div x-show="isUploading" x-cloak class="mt-3 h-1 bg-slate-100 rounded-full overflow-hidden">
                            <div class="progress-bar h-full bg-gradient-to-r from-gov-blue to-gov-blue-light rounded-full"></div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="stat-card rounded-xl p-4 shadow-sm" style="border-left-color: #f59e0b;">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Total Avidavit</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1">{{ $totalCount }}</p>
                </div>
                <div class="stat-card rounded-xl p-4 shadow-sm" style="border-left-color: #3b82f6;">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Laki-Laki</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1">{{ \App\Models\ParsedAvidavitDocument::where('jenis_kelamin','L')->count() }}</p>
                </div>
                <div class="stat-card rounded-xl p-4 shadow-sm" style="border-left-color: #ec4899;">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Perempuan</p>
                    <p class="text-2xl font-bold text-pink-500 mt-1">{{ \App\Models\ParsedAvidavitDocument::where('jenis_kelamin','P')->count() }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== SECTION: DATA TABLE ===== -->
    <section id="section-data" class="hidden">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-amber-50/60">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-gov-blue flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            Data Avidavit
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $totalCount }} total record tersimpan</p>
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <form method="GET" action="{{ route('avidavit.index') }}" class="flex-1 sm:flex-none">
                            <input type="hidden" name="tab" value="data">
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, register, paspor..."
                                    class="pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-lg w-64 focus:outline-none focus:ring-2 focus:ring-gov-blue-light focus:border-transparent"
                                    oninput="this.form.submit()">
                            </div>
                        </form>
                        @if($totalCount > 0)
                        <a href="{{ route('avidavit.export') }}" class="btn-primary flex items-center gap-2 px-4 py-2 text-white text-xs font-semibold rounded-lg shadow-md whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export ke Excel
                        </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @if($documents->isEmpty())
                <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                    <svg class="w-16 h-16 mb-4 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p class="text-sm font-medium">Belum ada data tersimpan</p>
                    <p class="text-xs mt-1">Upload file PDF atau Word untuk memulai ekstraksi data</p>
                </div>
                @else
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-gov-blue text-white">
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">No</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Nama</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">TTL</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Kewarganegaraan</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">No. Paspor Asing</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Alamat</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Nama Ayah</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">WN Ayah</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Nama Ibu</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">WN Ibu</th>
                            <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">No. Register</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">JK</th>
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($documents as $index => $doc)
                        <tr class="{{ $index % 2 == 0 ? 'bg-white' : 'bg-slate-50/50' }}">
                            <td class="px-3 py-2.5 text-slate-500 font-medium whitespace-nowrap">{{ ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration }}</td>
                            <td class="px-3 py-2.5 font-semibold text-gov-blue whitespace-nowrap max-w-[150px] truncate" title="{{ $doc->nama }}">{{ $doc->nama ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap max-w-[150px] truncate" title="{{ $doc->ttl }}">{{ $doc->ttl ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $doc->kewarganegaraan ?? '-' }}</td>
                            <td class="px-3 py-2.5 font-mono text-slate-700 whitespace-nowrap">{{ $doc->nomor_paspor_asing ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 max-w-[180px] truncate" title="{{ $doc->alamat }}">{{ $doc->alamat ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $doc->nama_ayah ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $doc->kewarganegaraan_ayah ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $doc->nama_ibu ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-slate-600 whitespace-nowrap">{{ $doc->kewarganegaraan_ibu ?? '-' }}</td>
                            <td class="px-3 py-2.5 font-mono text-slate-700 whitespace-nowrap">{{ $doc->no_register ?? '-' }}</td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                @if($doc->jenis_kelamin)
                                <span class="font-bold {{ $doc->jenis_kelamin === 'L' ? 'text-blue-600' : 'text-pink-500' }}">{{ $doc->jenis_kelamin }}</span>
                                @else <span class="text-slate-300">-</span> @endif
                            </td>
                            <td class="px-3 py-2.5 text-center whitespace-nowrap">
                                <form method="POST" action="{{ route('avidavit.destroy', $doc->id) }}" onsubmit="return confirm('Hapus data {{ addslashes($doc->nama ?? $doc->file_name) }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors" title="Hapus">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>

            @if($documents->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-slate-500">Menampilkan {{ $documents->firstItem() }}–{{ $documents->lastItem() }} dari {{ $documents->total() }} data</p>
                    <div class="flex items-center gap-1">
                        @if($documents->onFirstPage())
                        <span class="px-3 py-1.5 text-xs text-slate-300 border border-slate-200 rounded-lg">‹</span>
                        @else
                        <a href="{{ $documents->previousPageUrl() }}&tab=data{{ request('search') ? '&search='.request('search') : '' }}" class="px-3 py-1.5 text-xs text-gov-blue border border-gov-blue-light/40 rounded-lg hover:bg-gov-blue-pale transition-colors">‹</a>
                        @endif
                        @foreach($documents->getUrlRange(max(1, $documents->currentPage()-2), min($documents->lastPage(), $documents->currentPage()+2)) as $page => $url)
                        <a href="{{ $url }}&tab=data{{ request('search') ? '&search='.request('search') : '' }}" class="px-3 py-1.5 text-xs rounded-lg transition-colors {{ $page == $documents->currentPage() ? 'bg-gov-blue text-white' : 'text-gov-blue border border-gov-blue-light/40 hover:bg-gov-blue-pale' }}">{{ $page }}</a>
                        @endforeach
                        @if($documents->hasMorePages())
                        <a href="{{ $documents->nextPageUrl() }}&tab=data{{ request('search') ? '&search='.request('search') : '' }}" class="px-3 py-1.5 text-xs text-gov-blue border border-gov-blue-light/40 rounded-lg hover:bg-gov-blue-pale transition-colors">›</a>
                        @else
                        <span class="px-3 py-1.5 text-xs text-slate-300 border border-slate-200 rounded-lg">›</span>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </section>

@endsection

@section('scripts')
<script>
    function showSection(name) {
        document.getElementById('section-upload').classList.toggle('hidden', name !== 'upload');
        document.getElementById('section-data').classList.toggle('hidden', name !== 'data');
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('border-gov-blue-light', 'text-gov-blue');
            btn.classList.add('border-transparent', 'text-slate-500');
        });
        const activeTab = document.getElementById('tab-' + name);
        activeTab.classList.remove('border-transparent', 'text-slate-500');
        activeTab.classList.add('border-gov-blue-light', 'text-gov-blue');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'data' || urlParams.has('search')) {
            showSection('data');
        }
        @if(request('tab') === 'data' || request('search'))
        showSection('data');
        @endif
    });

    function uploadZone() {
        return {
            files: [],
            isDragging: false,
            isUploading: false,
            handleDrop(event) {
                this.isDragging = false;
                const droppedFiles = Array.from(event.dataTransfer.files).filter(f =>
                    f.type === 'application/pdf' || f.name.endsWith('.docx') || f.name.endsWith('.doc')
                );
                this.addFiles(droppedFiles);
            },
            handleFileSelect(event) {
                this.addFiles(Array.from(event.target.files));
            },
            addFiles(newFiles) {
                newFiles.forEach(file => {
                    if (!this.files.find(f => f.name === file.name && f.size === file.size)) {
                        this.files.push(file);
                    }
                });
                this.syncInputFiles();
            },
            removeFile(index) {
                this.files.splice(index, 1);
                this.syncInputFiles();
            },
            clearFiles() {
                this.files = [];
                this.$refs.fileInput.value = '';
            },
            syncInputFiles() {
                const dt = new DataTransfer();
                this.files.forEach(f => dt.items.add(f));
                this.$refs.fileInput.files = dt.files;
            },
            formatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
            },
            submitForm() {
                if (this.files.length === 0) return;
                this.isUploading = true;
                document.getElementById('upload-form').submit();
            }
        }
    }
</script>
@endsection
