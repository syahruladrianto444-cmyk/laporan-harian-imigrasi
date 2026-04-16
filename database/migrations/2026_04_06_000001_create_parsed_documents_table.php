<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parsed_documents', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('nama')->nullable();
            $table->string('kebangsaan')->nullable();
            $table->string('jenis_kelamin', 1)->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('nomor_paspor')->nullable();
            $table->date('tanggal_expired_paspor')->nullable();
            $table->string('tipe_dokumen')->nullable()->comment('ITAS/ITK/IMK/TSP-EPO/ITAP');
            $table->string('nomor_dokumen')->nullable();
            $table->date('tanggal_expired_itas')->nullable();
            $table->text('alamat')->nullable();
            $table->string('penjamin')->nullable()->comment('Sponsor/Guarantor');
            $table->date('tanggal_terbit')->nullable()->comment('Tanggal Permohonan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parsed_documents');
    }
};
