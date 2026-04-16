<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parsed_word_documents', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->integer('page_number')->default(1);
            $table->string('nama')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('kebangsaan')->nullable();
            $table->text('alamat')->nullable();
            $table->string('kota_kabupaten')->nullable();
            $table->date('masa_berlaku')->nullable();
            $table->string('nomor_paspor')->nullable();
            $table->string('nomor_registrasi')->nullable();
            $table->string('tiket_pulang')->nullable()->default('-');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parsed_word_documents');
    }
};
