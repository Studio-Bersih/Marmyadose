<?php

namespace App\Models\Kosada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdministratorModel extends Model
{
    use HasFactory;
    protected $table = 'kosada_member';
    protected $primaryKey = 'ID';
    protected $fillable = ['NAMA', 'ALAMAT', 'KOTA', 'PROVINSI', 'TELEPON', 'KTP', 'PIN_ATM', 'GENDER', 'DATA_MARKETING', 'PEKERJAAN', 'REKOMENDASI_DARI', 'KETERANGAN'];
}
