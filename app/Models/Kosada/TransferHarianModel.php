<?php

namespace App\Models\Kosada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferHarianModel extends Model
{
    use HasFactory;

    protected $table = 'kosada_transfer_harian';
    protected $primaryKey = 'ID';

    const CREATED_AT = 'CREATED_AT';
    const UPDATED_AT = 'UPDATED_AT';

    /**
     * CREATED_AT is deliberately absent from $fillable.
     *
     * It records when the row was really inserted, and the page compares it
     * against TANGGAL_TRANSFER to flag entries made after the fact. If a client
     * could set it, that check would be meaningless.
     */
    protected $fillable = [
        'TANGGAL_TRANSFER','MEMBER_ID','KREDIT_ID',
        'NAMA','INSTANSI','JENIS','NOMINAL','KETERANGAN',
    ];

    public function member()
    {
        return $this->belongsTo(AdministratorModel::class, 'MEMBER_ID', 'ID');
    }

    public function kredit()
    {
        return $this->belongsTo(KreditModel::class, 'KREDIT_ID', 'ID');
    }
}
