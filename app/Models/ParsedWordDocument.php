<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParsedWordDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'page_number',
        'nama',
        'jenis_kelamin',
        'kebangsaan',
        'alamat',
        'kota_kabupaten',
        'masa_berlaku',
        'nomor_paspor',
        'nomor_registrasi',
        'tiket_pulang',
    ];

    protected $casts = [
        'masa_berlaku' => 'date',
    ];
}
