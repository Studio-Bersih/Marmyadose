<?php

namespace App\Models\Kosada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KreditModel extends Model
{
    use HasFactory;
    protected $table = 'kosada_kredit';
    protected $primaryKey = 'ID';
    protected $fillable = ['NO_KREDIT','MEMBER_ID','NAMA','ALAMAT','MARKETING',
    'JUMLAH_PENGAJUAN','JANGKA_WAKTU','JATUH_TEMPO','KETERANGAN','STATUS','LUNAS_BRP','ADMIN'];

    /**
     * The member this loan belongs to.
     *
     * MEMBER_ID is nullable: loans created before the column existed may not have
     * matched a member during the backfill. Always use this with a left join or a
     * null check — NAMA and ALAMAT are also stored on the loan itself as a fallback,
     * but PEKERJAAN and TELEPON are only reachable through here.
     */
    public function member()
    {
        return $this->belongsTo(AdministratorModel::class, 'MEMBER_ID', 'ID');
    }
}
