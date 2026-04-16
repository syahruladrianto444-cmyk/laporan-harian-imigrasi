<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParsedDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'nama',
        'kebangsaan',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'nomor_paspor',
        'tanggal_expired_paspor',
        'tipe_dokumen',
        'nomor_dokumen',
        'tanggal_expired_itas',
        'alamat',
        'penjamin',
        'tanggal_terbit',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_expired_paspor' => 'date',
        'tanggal_expired_itas' => 'date',
        'tanggal_terbit' => 'date',
    ];
}
