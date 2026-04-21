<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParsedSkimDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'nama',
        'ttl',
        'niora',
        'status_sipil',
        'kewarganegaraan',
        'pekerjaan',
        'nomor_paspor',
        'jenis_keimigrasian',
        'alamat',
        'no_register',
        'jenis_kelamin',
    ];
}
