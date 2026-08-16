<?php

namespace App\Models\Kosada;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KreditMacetModel extends Model
{
    use HasFactory;

    protected $table = 'kosada_kredit_macet';
    protected $primaryKey = 'ID';

    const CREATED_AT = 'CREATED_AT';
    const UPDATED_AT = 'UPDATED_AT';

    protected $fillable = [
        'KREDIT_ID','NO_KREDIT','MEMBER_ID','ALASAN_MACET',
        'STATUS','TANGGAL_MACET','TANGGAL_SELESAI','ALASAN_SELESAI',
    ];

    /**
     * The loan that went bad. One macet record per loan, never per person.
     */
    public function kredit()
    {
        return $this->belongsTo(KreditModel::class, 'KREDIT_ID', 'ID');
    }

    /**
     * The borrower. Nullable — loans whose MEMBER_ID could not be resolved during
     * the backfill fall back to the NAMA/ALAMAT stored on the loan itself, and
     * show "-" for Pekerjaan and WhatsApp.
     */
    public function member()
    {
        return $this->belongsTo(AdministratorModel::class, 'MEMBER_ID', 'ID');
    }
}
