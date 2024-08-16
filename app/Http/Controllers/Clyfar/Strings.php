<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Strings extends Controller
{
    const DISC = [
        "RAW_MOST" => [
            "1" => ["D" => 0, "I" => 2, "S" => 1, "C" => 4],
            "2" => ["D" => 1, "I" => 0, "S" => 0, "C" => 2],
            "3" => ["D" => 2, "I" => 4, "S" => 3, "C" => 0],
            "4" => ["D" => 2, "I" => 0, "S" => 4, "C" => 1],
            "5" => ["D" => 2, "I" => 4, "S" => 3, "C" => 0],
            "6" => ["D" => 1, "I" => 0, "S" => 0, "C" => 4],
            "7" => ["D" => 4, "I" => 1, "S" => 0, "C" => 0],
            "8" => ["D" => 3, "I" => 0, "S" => 1, "C" => 4],
            "9" => ["D" => 1, "I" => 3, "S" => 2, "C" => 0],
            "10" => ["D" => 4, "I" => 0, "S" => 2, "C" => 1],
            "11" => ["D" => 4, "I" => 3, "S" => 0, "C" => 2],
            "12" => ["D" => 1, "I" => 3, "S" => 2, "C" => 4],
            "13" => ["D" => 2, "I" => 1, "S" => 3, "C" => 0],
            "14" => ["D" => 1, "I" => 2, "S" => 3, "C" => 0],
            "15" => ["D" => 2, "I" => 3, "S" => 1, "C" => 0],
            "16" => ["D" => 2, "I" => 3, "S" => 4, "C" => 1],
            "17" => ["D" => 4, "I" => 2, "S" => 3, "C" => 1],
            "18" => ["D" => 3, "I" => 0, "S" => 1, "C" => 4],
            "19" => ["D" => 0, "I" => 2, "S" => 1, "C" => 0],
            "20" => ["D" => 4, "I" => 3, "S" => 1, "C" => 2],
            "21" => ["D" => 0, "I" => 2, "S" => 3, "C" => 0],
            "22" => ["D" => 4, "I" => 1, "S" => 2, "C" => 3],
            "23" => ["D" => 0, "I" => 3, "S" => 4, "C" => 2],
            "24" => ["D" => 3, "I" => 2, "S" => 0, "C" => 4],
        ],
        "RAW_LEAST" => [
            "1" => ["D" => 3, "I" => 2, "S" => 1, "C" => 4],
            "2" => ["D" => 1, "I" => 3, "S" => 4, "C" => 2],
            "3" => ["D" => 2, "I" => 0, "S" => 3, "C" => 1],
            "4" => ["D" => 2, "I" => 3, "S" => 4, "C" => 0],
            "5" => ["D" => 2, "I" => 0, "S" => 3, "C" => 1],
            "6" => ["D" => 1, "I" => 2, "S" => 3, "C" => 0],
            "7" => ["D" => 0, "I" => 1, "S" => 3, "C" => 2],
            "8" => ["D" => 3, "I" => 2, "S" => 0, "C" => 4],
            "9" => ["D" => 1, "I" => 3, "S" => 0, "C" => 4],
            "10" => ["D" => 4, "I" => 3, "S" => 2, "C" => 1],
            "11" => ["D" => 4, "I" => 3, "S" => 1, "C" => 0],
            "12" => ["D" => 0, "I" => 3, "S" => 2, "C" => 0],
            "13" => ["D" => 2, "I" => 0, "S" => 3, "C" => 4],
            "14" => ["D" => 1, "I" => 0, "S" => 0, "C" => 4],
            "15" => ["D" => 2, "I" => 3, "S" => 1, "C" => 4],
            "16" => ["D" => 2, "I" => 3, "S" => 4, "C" => 0],
            "17" => ["D" => 4, "I" => 2, "S" => 0, "C" => 1],
            "18" => ["D" => 3, "I" => 2, "S" => 1, "C" => 4],
            "19" => ["D" => 4, "I" => 2, "S" => 0, "C" => 3],
            "20" => ["D" => 4, "I" => 3, "S" => 1, "C" => 0],
            "21" => ["D" => 1, "I" => 0, "S" => 3, "C" => 4],
            "22" => ["D" => 4, "I" => 1, "S" => 2, "C" => 3],
            "23" => ["D" => 1, "I" => 3, "S" => 4, "C" => 0],
            "24" => ["D" => 0, "I" => 2, "S" => 1, "C" => 0],
        ]
    ];

    const RMIB = [
        "out" => [
            "DESC" => "Pekerjaan yang aktivitasnya dilakukan di luar atau di lapangan terbuka",
            "Pria" => "petani, juru ukur, nelayan, supir.",
            "Wanita" => "ahli pertamanan, peternak, petani bunga dan tukang kebun"
        ],
        "me" => [
            "DESC" => "Pekerjaan yang berhubungan dengan mesin, alat-alat dan daya mekanik.",
            "Pria" => "insinyur sipil, montir, pembuat arloji, tukang las.",
            "Wanita" => "ahli kacamata, petugas mesin sulam, ahli reparasi permata, ahli reparasi jam."
        ],
        "comp" => [
            "DESC" => "Pekerjaan yang berhubungan dengan angka-angka.",
            "Pria" => "akuntan, auditor, kasir, petugas pajak.",
            "Wanita" => "pegawai urusan gaji, juru bayar, pegawai pajak, guru ilmu pasti."
        ],
        "sci" => [
            "DESC" => "Pekerjaan yang dapat disebut sebagai keaktifan dalam hal analisa dan penyelidikan, eksperimen, kimia dan ilmu pengetahuan pada umumnya.",
            "Pria" => "ilmuwan, ahli biologi, ahli astronomi dan insinyur kimia industri",
            "Wanita" => "-"
        ],
        "prs" => [
            "DESC" => "Pekerjaan yang berhubungan dengan manusia, diskusi, membujuk, bergaul dengan orang lain. Pada dasarnya adalah suatu pekerjaan yang membutuhkan kontak dengan orang lain.",
            "Pria" => "penyiar radio, petugas wawancara, sales asuransi, pedagang keliling.",
            "Wanita" => "sales girl, pegawai rumah mode, penyiar radio, petugas humas."
        ],
        "aesth" => [
            "DESC" => "Pekerjaan yang berhubungan dengan hal-hal yang bersifat seni dan menciptakan sesuatu.",
            "Pria" => "artis, arsitek, dekorator, fotografer dan piñata panggung",
            "Wanita" => "seniwati, guru kesenian, artis, piñata panggung"
        ],
        "lit" => [
            "DESC" => "Pekerjaan yang berhubungan dengan buku-buku, kegiatan membaca dan mengarang.",
            "Pria" => "wartawan, pengarang, penulis skenario, ahli perpustakaan, penulis majalah.",
            "Wanita" => "wartawan, kritikus buku, penyair, penulis sandiwara radio."
        ],
        "mus" => [
            "DESC" => "Minat memainkan alat-alat musik atau untuk mendengarkan orang lain, bernyanyi atau membaca sesuatu yang berhubungan musik.",
            "Pria" => "pianis konser, komponis, pemain organ, ahli pustaka dan pramuniaga toko musik.",
            "Wanita" => "pemain organ, guru musik, komponis, pianis konser, pramuniaga toko musik"
        ],
        "ss" => [
            "DESC" => "Minat terhadap kesejahteraan penduduk dengan keinginan untuk menolong dan membimbing atau menasehati tentang problem dan kesulitan mereka. Keinginan untuk mengerti orang lain, dan mempunyai ide yang besar atau kuat tentang pelayanan.",
            "Pria" => "guru SD, psikolog pendidikan, kepala sekolah, penyebar agama, petugas palang merah.",
            "Wanita" => "guru SD, psikolog pendidikan, petugas kesejahteraan sosial, ahli penyuluh jabatan, petugas palang merah."
        ],
        "cler" => [
            "DESC" => "Minat terhadap tugas-tugas rutin yang menuntut ketepatan dan ketelitian.",
            "Pria" => "manajer bank, petugas arsip, petugas pengiriman barang, pegawai kantor, petugas pos, petugas ekspedisi (surat).",
            "Wanita" => "sekretaris pribadi, juru ketik, penulis steno, pegawai kantor, penyusun arsip."
        ],
        "prac" => [
            "DESC" => "Minat terhadap pekerjaan-pekerjaan yang praktis, karya pertukangan, dan yang memerlukan keterampilan.",
            "Pria" => "tukang kayu, ahli bangunan, ahli mebel, tukang cat, tukang batu, tukang sepatu.",
            "Wanita" => "ahli piñata rambut, tukang bungkus coklat, tukang binatu, penjahit, petugas mesin sulam, juru masak"
        ],
        "med" => [
            "DESC" => "Minat terhadap pengobatan, mengurangi akibat dari penyakit, penyembuhan, dan di dalam bidang medis, serta terhadap hal-hal biologis pada umumnya.",
            "Pria" => "dokter, ahli bedah, dokter hewan, ahli farmasi, dokter gigi, ahli kacamata, ahli rontgen.",
            "Wanita" => "dokter, ahli bedah, dokter hewan, pelatih rehabilitasi pasien, perawat orang tua."
        ]
    ];

    const KRAEPLIN = [
        [3, 1, 9, 1, 0, 4, 7, 1, 1, 4, 1, 8, 4, 0, 9, 2, 3, 4, 1, 5, 2, 0, 1, 6, 6, 4, 9], 
        [1, 9, 7, 7, 8, 2, 3, 4, 8, 6, 0, 1, 4, 1, 7, 8, 9, 9, 8, 4, 0, 8, 3, 5, 5, 1, 4], 
        [0, 0, 2, 2, 1, 4, 9, 0, 4, 2, 2, 3, 2, 6, 3, 2, 0, 7, 7, 5, 9, 5, 5, 2, 0, 6, 6], 
        [1, 0, 6, 0, 6, 5, 2, 8, 8, 2, 5, 5, 1, 2, 6, 5, 3, 8, 5, 0, 4, 3, 1, 7, 6, 7, 5], 
        [4, 4, 7, 5, 8, 2, 6, 0, 2, 4, 2, 2, 1, 8, 3, 0, 6, 7, 1, 5, 0, 1, 6, 9, 3, 7, 9], 
        [8, 8, 0, 0, 5, 3, 7, 7, 1, 2, 4, 6, 5, 6, 6, 8, 5, 0, 5, 7, 9, 8, 3, 5, 1, 0, 4], 
        [2, 8, 7, 2, 1, 1, 0, 3, 5, 1, 8, 5, 6, 6, 9, 9, 7, 4, 3, 1, 8, 4, 6, 2, 1, 7, 9], 
        [2, 0, 6, 4, 1, 8, 6, 7, 8, 6, 5, 8, 8, 2, 4, 6, 5, 6, 3, 9, 6, 7, 7, 9, 6, 3, 0], 
        [7, 5, 8, 7, 4, 7, 5, 0, 8, 4, 2, 3, 0, 8, 3, 6, 0, 2, 5, 7, 5, 7, 7, 8, 1, 2, 4], 
        [8, 2, 0, 5, 1, 5, 4, 5, 3, 5, 4, 9, 9, 5, 9, 1, 7, 5, 7, 9, 9, 4, 0, 3, 8, 0, 0], 
        [9, 0, 0, 8, 1, 7, 3, 2, 3, 3, 6, 2, 1, 8, 6, 9, 7, 2, 4, 9, 7, 7, 2, 1, 0, 6, 8], 
        [6, 7, 4, 2, 8, 8, 9, 5, 9, 4, 6, 8, 2, 6, 5, 9, 5, 3, 0, 7, 3, 0, 2, 0, 3, 5, 3], 
        [4, 8, 5, 7, 3, 6, 7, 2, 0, 8, 9, 0, 8, 5, 9, 9, 1, 4, 4, 7, 4, 8, 1, 4, 9, 5, 6], 
        [8, 7, 9, 9, 4, 6, 2, 1, 6, 2, 8, 0, 2, 5, 0, 5, 1, 7, 0, 4, 4, 3, 7, 8, 1, 5, 6], 
        [3, 7, 0, 0, 9, 2, 4, 8, 3, 5, 3, 7, 6, 6, 3, 4, 4, 9, 9, 8, 1, 7, 2, 8, 4, 6, 1], 
        [9, 1, 6, 8, 9, 0, 3, 0, 2, 9, 5, 2, 5, 0, 2, 4, 7, 8, 2, 9, 2, 8, 2, 1, 9, 0, 4], 
        [0, 9, 4, 0, 8, 0, 3, 3, 1, 9, 6, 3, 9, 1, 3, 4, 3, 3, 3, 1, 0, 8, 5, 0, 4, 0, 1], 
        [7, 2, 1, 4, 8, 1, 9, 4, 5, 1, 9, 6, 5, 6, 8, 2, 2, 2, 0, 6, 1, 1, 2, 2, 4, 7, 4], 
        [3, 8, 8, 3, 0, 6, 1, 3, 0, 2, 9, 8, 7, 0, 2, 3, 0, 0, 0, 8, 6, 3, 6, 9, 8, 2, 3], 
        [9, 6, 4, 8, 7, 9, 0, 0, 4, 0, 7, 0, 5, 6, 3, 5, 4, 1, 9, 2, 5, 6, 9, 8, 1, 2, 4], 
        [7, 9, 1, 0, 3, 0, 5, 8, 4, 9, 5, 2, 7, 7, 4, 3, 9, 9, 6, 0, 5, 5, 8, 2, 8, 4, 6], 
        [3, 6, 1, 7, 0, 4, 3, 8, 8, 0, 3, 6, 9, 6, 2, 9, 5, 0, 8, 1, 1, 2, 4, 7, 3, 3, 0], 
        [8, 3, 0, 0, 0, 7, 5, 5, 8, 4, 3, 5, 8, 7, 5, 1, 5, 9, 1, 4, 8, 4, 4, 1, 8, 1, 7], 
        [3, 6, 1, 3, 5, 4, 6, 4, 1, 5, 3, 1, 2, 6, 6, 1, 6, 6, 5, 0, 0, 5, 2, 5, 1, 9, 4], 
        [9, 0, 0, 3, 7, 5, 9, 1, 3, 0, 5, 9, 0, 2, 8, 1, 0, 5, 4, 8, 6, 7, 4, 8, 1, 8, 6], 
        [9, 9, 5, 5, 4, 9, 5, 4, 4, 4, 4, 4, 8, 2, 4, 0, 1, 8, 8, 8, 5, 4, 3, 3, 5, 1, 9], 
        [3, 0, 1, 2, 3, 3, 1, 8, 4, 0, 6, 5, 8, 0, 5, 4, 6, 0, 8, 5, 3, 4, 1, 3, 6, 1, 7], 
        [2, 1, 1, 3, 2, 3, 4, 9, 1, 8, 3, 7, 4, 3, 6, 1, 3, 0, 1, 6, 4, 0, 9, 6, 1, 9, 5], 
        [6, 5, 7, 2, 0, 7, 2, 2, 9, 2, 6, 3, 1, 4, 0, 9, 1, 7, 5, 5, 9, 4, 8, 8, 0, 4, 9], 
        [1, 1, 5, 0, 5, 0, 6, 3, 4, 8, 7, 3, 5, 1, 1, 1, 6, 4, 9, 0, 0, 4, 4, 0, 7, 2, 4], 
        [1, 0, 3, 4, 5, 3, 6, 7, 8, 1, 2, 8, 8, 6, 7, 6, 5, 1, 0, 1, 6, 8, 3, 8, 8, 2, 2], 
        [0, 6, 2, 9, 4, 4, 7, 0, 8, 2, 4, 4, 4, 5, 1, 1, 6, 1, 6, 0, 0, 5, 0, 9, 3, 9, 2], 
        [5, 6, 1, 8, 3, 5, 8, 0, 2, 2, 1, 5, 8, 4, 5, 4, 0, 9, 5, 5, 0, 5, 7, 7, 1, 2, 0], 
        [5, 2, 6, 8, 3, 4, 2, 3, 8, 0, 8, 4, 6, 4, 7, 3, 2, 4, 5, 0, 8, 5, 4, 6, 4, 1, 2], 
        [4, 5, 3, 3, 6, 0, 5, 3, 6, 7, 6, 8, 8, 1, 5, 1, 5, 8, 2, 9, 0, 3, 3, 6, 0, 6, 8], 
        [6, 1, 2, 6, 6, 2, 6, 4, 0, 3, 2, 5, 3, 8, 5, 4, 5, 7, 2, 0, 0, 5, 5, 1, 5, 7, 9], 
        [3, 5, 9, 5, 2, 1, 0, 1, 5, 4, 7, 5, 1, 7, 4, 7, 2, 3, 4, 3, 1, 4, 9, 2, 2, 3, 9], 
        [5, 4, 7, 9, 9, 7, 8, 7, 9, 1, 5, 8, 3, 9, 4, 0, 0, 6, 6, 8, 8, 6, 3, 1, 5, 1, 0], 
        [8, 1, 8, 2, 2, 0, 0, 9, 7, 6, 4, 6, 1, 1, 6, 8, 1, 7, 3, 8, 3, 3, 0, 0, 0, 2, 6], 
        [1, 7, 0, 1, 6, 1, 0, 8, 2, 0, 3, 5, 5, 9, 2, 6, 7, 6, 6, 4, 0, 2, 8, 7, 0, 1, 7], 
        [7, 2, 3, 9, 7, 2, 1, 2, 2, 6, 7, 3, 0, 0, 4, 0, 2, 2, 8, 2, 7, 3, 4, 6, 1, 6, 6], 
        [7, 4, 2, 7, 1, 1, 5, 3, 3, 9, 2, 0, 1, 9, 8, 7, 5, 6, 6, 9, 8, 4, 0, 2, 0, 8, 3], 
        [5, 0, 5, 0, 7, 3, 5, 2, 2, 8, 3, 5, 0, 5, 6, 5, 4, 6, 4, 8, 5, 7, 0, 5, 9, 6, 3], 
        [7, 6, 7, 2, 4, 2, 9, 7, 9, 3, 3, 6, 5, 6, 4, 4, 7, 4, 1, 9, 4, 8, 9, 9, 3, 6, 1], 
        [1, 9, 5, 1, 5, 4, 2, 7, 1, 7, 6, 4, 8, 4, 5, 7, 7, 4, 6, 7, 0, 4, 2, 3, 5, 2, 1], 
        [9, 2, 8, 6, 5, 7, 3, 4, 8, 5, 7, 9, 6, 8, 9, 9, 7, 9, 3, 3, 5, 8, 6, 0, 4, 2, 4], 
        [1, 6, 6, 3, 3, 0, 9, 6, 0, 0, 0, 5, 1, 0, 0, 1, 4, 8, 6, 2, 5, 3, 3, 1, 6, 6, 2], 
        [6, 6, 6, 9, 7, 7, 0, 9, 3, 4, 1, 0, 3, 7, 7, 5, 2, 7, 6, 8, 9, 2, 5, 8, 2, 5, 2], 
        [7, 8, 1, 4, 2, 8, 0, 7, 4, 9, 2, 8, 7, 4, 7, 9, 4, 8, 4, 6, 1, 2, 9, 8, 1, 2, 4], 
        [8, 2, 4, 5, 3, 1, 1, 8, 6, 9, 4, 3, 8, 2, 1, 5, 7, 2, 9, 9, 2, 8, 6, 4, 0, 0, 6]
    ];

    const MSDT = [
        "Deserter" => "Pendekatan gaya manajemen tipe ini adalah suka mengabaikan masalah, cuci tangan, tidak mau bertanggung jawab (laisser-faire). Tipe gaya ini mengabaikan berbagai keterlibatan atau intervensi yang dapat menjadikan situasi dianggap sulit atau rumit. Sikapnya selalu mencoba netral terhadap apa yang terjadi di keseharian, mencari jalan untuk menghindar dari aturan yang dianggap menyulitkan. Polanya adalah mencoba tetap menyelaraskan antara atasan dan bawahan, menghindari perubahan perencanaan. Pola yang tampak secara manajerial adalah defensif, misalkan ada kebijakan yang menyulitkan bawahan maka ia mengatakan saya hanya menjalankan perintah, kebijakan dari atasan. Bukan berarti pola seperti ini buruk, deserter hanya berupaya menjaga keadaan status-quo dan menghindari perubahan drastis atau “guncangan dalam manajemen”.",
        "Autocrat" => "Gaya seperti ini lebih perhatian hanya pada produktivitas dan hasil. Skor tinggi dianggap sebagai manajer yang formal, memberikan tugas ke bawahan berdasarkan instruksi dan mengawasi secara ketat proses yang terjadi. Kesalahan tidak bisa ditolerir, penyimpangan harus dihindari… yang penting jangan sampai salah dalam mengerjakan sesuatu. Kebijakan adalah urusan atasan sementara bawahan cukup melaksanakan apa yang harus dikerjakan tanpa ada alasan karena dianggap tidak perlu dan membuang waktu. Gaya ini meminimalisir komunikasi, membatasi terhadap apa yang perlu saja. Bawahan akan menganggap dingin atasan dengan gaya ini, terutama bagi mereka yang membutuhkan lebih dari sekadar tugas yang harus dikerjakan seperti dorongan akan pengakuan atau dukungan. Model pendekatan pengendalian dan pengarahan dianggap kurang efektif, karena kaku, keras kepala sehingga bawahan akan merasa tertekan.",
        "Compromiser" => "Gaya ini mengandalkan tugas dan relasi yang seimbang, namun dianggap kurang efektif karena tidak berpendirian tetap, tidak ada keputusan yang jelas. Gaya ini akan merasa kebingungan antara pengaturan tugas dan kebutuhan untuk berinteraksi. Dalam menghadapi tekanan, maka akan cenderung kompromi sehingga berbagai tujuan seringkali menyimpang dan tidak tercapai.",
        "Missionary" => "Pendekatan gaya manajemen seperti ini adalah menggunakan unsur afektif yang sangat kental. Missionary berupaya mendorong situasi positif dalam manajemen dengan memberikan kandungan sensitivitas, kepedulian dan hal-hal yang mungkin dianggap penting untuk meningkatkan kinerja melalui sentuhan emosi/perasaan. Model manajerial seperti ini berupaya menjaga orang lain termasuk bawahan pada situasi bahagia dalam situasi apapun. Perilaku mendorong atau mengajak menunjukkan bagian penting dari gaya yang ditunjukkan. Mengapa dikatakan kurang efektif gaya manajemen seperti ini adalah karena kurang ketersediaanya peluang konflik, berupaya tetap halus dalam bertindak dan kesulitan untuk menolak atau berkata tidak, padahal banyak pekerjaan perlu ketegasan dalam manajemen.",
        "Bureaucrat" => "Pendekatan gaya manajemen ini adalah prosedural, berdasarkan aturan atau tata pelaksanaan, menerima dengan tulus hirarki kewenangan dan menggunakan komunikasi sangat formal dalam bersikap. Skor yang tinggi berarti sistematik. Fungsi dan peran birokrat akan sangat optimal pada situasi yang terstruktur dengan pola prosedur yang jelas meskipun dapat saja prosedur yang ada sebenarnya rumit, namun birokrat akan tetap tenang menghadapi sistem yang ada. Birokrat berpegang pada sistem, gaya manajemen seperti ini tampak seperti otokrat, kaku dan dapat membosankan bagi orang-orang yang fleksibel",
        "Benevolent Autocrat" => "Gaya ini dianggap efektif karena memberikan unsur komunikatif dalam melakukan gaya otokratik. Gaya ini masih mengandalkan instruksi dan intervensi. Skor tinggi dapat dilihat sebagai guru dalam memberi tugas, dimaana dapat memberikan instruksi dengan tidak mengesampingkan komunikasi kepada bawahan secara lebih fleksibel. Pola yang dilakukan memberikan kesediaan untuk bertanya, membantu apabila ada hal yang dianggap salah atau menyimpang. Pola keseharian terstruktur dalam menentukan target kerja, produktivitas dan memberi perintah, tidak ragu memberikan hukuman namun bertindak adil dalam menyikapinya. Gaya ini dapat bekerjasama dengan baik namun menghindari hubungan keterdekatan antar personal",
        "Developer" => "Gaya manajemen developer adalah sisi efektif dari gaya missionary. Tujuan dari gaya seperti ini adalah untuk bertindak secara profesional tanpa mengesampingkan aspek emosi. Bawahan diberikan kesempatan untuk memberikan ide, pandangan atau peran lebih dari kebijakan yang ada untuk mengembangkan potensi. Kontribusi diberikan dan perhatian untuk pengembangan pun diperhatikan. Skor tinggi memiliki keyakinan optimis tentang individu untuk bekerja dan menghasilkan. Sifat pendekatan berupa kolegial, bawahan sebagai partner bukan hanya sebagai “pembantu” dalam mengerjakan sesuatu. Gaya seperti ini senang untuk berbagi pengetahuan dan keahlian dan potensi bawahan dapat dioptimalkan",
        "Executive" => "Gaya ini dianggap efektif karena dapat mengelola dengan baik antara tugas dan hubungan. Model ini adalah sisi efektif dari gaya kompromis. Pola yang dilakukan dapat mengintegrasikan antara tugas dan hubungan dengan baik, mengelola dan memanfaatkan kedua aspek dengan sinergi yang optimal. Pendekatan ini dapat dikatakan sebagai pendekatan konsultatif, interaktif dan pemecah masalah. Pendekatan ini memanfaatkan eksplorasi terhadap berbagai sumber daya, keragaman informasi dan dapat memanfaatkan isu negatif menjadi dorongan untuk hasil yang lebih optimal. Gaya ini melibatkan tim dalam perencanaan dan mengambil kesimpulan. Komunikasi dilakukan terhadap bawahan untuk meningkatkan kualitas informasi yang dapat menjadikan keputusan lebih baik. Manajer dengan gaya seperti ini dapat dianggap sebagai motivator karena terbuka dengan berbagai hal baik yang mendukung atau menentang untuk mendapakan komitmen bersama"
    ];

    const CFIT = [
        "kunci1" => ["b", "c", "b", "d", "e", "b", "d", "b", "f", "c", "b", "b", "d"],
        "kunci2" => ["be", "ae", "ad", "ce", "be", "ad", "be", "be", "ad", "bd", "ae", "cd", "bc", "ab"],
        "kunci3" => ["e", "e", "e", "b", "c", "d", "e", "e", "a", "a", "f", "c", "c"],
        "kunci4" => ["b", "a", "d", "d", "a", "b", "c", "d", "a", "d"],
        "norma" => [
            38, 40, 43, 46, 47, 50, 53, 56, 58, 62, 65, 68, 70, 73, 75, 80, 83, 86, 89, 93, 94, 98, 
            101, 104, 108, 111, 114, 116, 119, 123, 124, 129, 131, 136, 139, 140, 144, 147, 150, 154, 
            155, 160, 163, 165, 168, 169, 173, 176, 179, 183
        ]
    ];
}
