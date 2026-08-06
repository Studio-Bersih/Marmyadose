<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Poin member
    |--------------------------------------------------------------------------
    |
    | Rupiah of cash payment required to earn one point. A sale grants
    | floor(CASH / poin_per_rupiah) points.
    |
    | Changing this value affects future sales only. The number of points a
    | sale granted is stored on ud84_penjualan_rekap.POIN at the time of sale,
    | and a cancellation reverses that stored figure -- so an old sale always
    | gives back exactly what it gave, whatever this value is today.
    |
    | Sales predating the POIN column have it null; cancelling one of those
    | falls back to recomputing from CASH with the value below, and records
    | that it did so in ud84_transaksi_log.CATATAN_SISTEM.
    |
    */

    'poin_per_rupiah' => 1000000,

];
