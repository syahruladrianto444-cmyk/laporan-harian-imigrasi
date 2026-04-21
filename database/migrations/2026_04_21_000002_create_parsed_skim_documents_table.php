<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parsed_skim_documents', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->string('nama')->nullable();
            $table->string('ttl')->nullable()->comment('Tempat, Tanggal Lahir gabungan');
            $table->string('niora')->nullable();
            $table->string('status_sipil')->nullable();
            $table->string('kewarganegaraan')->nullable();
            $table->string('pekerjaan')->nullable();
            $table->string('nomor_paspor')->nullable();
            $table->string('jenis_keimigrasian')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_register')->nullable();
            $table->string('jenis_kelamin', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parsed_skim_documents');
    }
};
