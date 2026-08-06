<?php

namespace App\Models\Kosada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KreditDetailModel extends Model
{
    use HasFactory;
    protected $table = 'kosada_detail_kredit';
    protected $primaryKey = 'ID';
    protected $fillable = ['NO_KREDIT','NAMA','JATUH_TEMPO','JATUH_TEMPO','LUNAS','STATUS','NOMINAL'];
}
