<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParsedAbgDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'nama',
        'ttl',
        'kewarganegaraan',
        'nomor_paspor_asing',
        'alamat',
        'nama_ayah',
        'kewarganegaraan_ayah',
        'nama_ibu',
        'kewarganegaraan_ibu',
        'no_register',
        'jenis_kelamin',
    ];
}
