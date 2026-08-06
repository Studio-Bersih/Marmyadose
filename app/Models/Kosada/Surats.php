<?php

namespace App\Models\Kosada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Surats extends Model
{
    use HasFactory;
    protected $table = 'kosada_surat_tugas';
    protected $primaryKey = 'ID';
    protected $fillable = ['NO','LAMPIRAN','HAL','TEKS'];
}
