<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Clyfar\Strings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\DTO\Responses;
use App\DTO\General;

class Result extends Controller
	{
		public function getTestee(): JsonResponse {
			$data = DB::table('clyfar_profile')->skip(0)->take(30)->orderByDesc('ID')->get();
			return response()->json(new Responses(
				"success", "Berhasil dimuat", $data
			));
		}

		public function createTestee(Request $request): JsonResponse {
			$amount = $request->input('amount');
			$test = $request->input('tests');

			$data = [];
			for($i = 0; $i < $amount; $i++) {
				$data[] = [
					"TOKEN"         => strtoupper(substr(Str::random(6), 0, 6)),
					"LIST"          => json_encode($test),
					"ENABLE_TEST"   => "Yes",
					"LEVEL"         => "Normal",
					"CREATED_AT"    => now()
				];
			}

			DB::table('clyfar_profile')->insert($data);

			return response()->json(new Responses(
				"success", "Akun berhasil dibuat"
			));
		}

		public function viewTestee(Request $request): JsonResponse {
			$token = $request->input('token');
			$DB = DB::table('clyfar_test')->where('KODE',$token)->first();
			
			if (empty($DB)) {
				return response()->json(new Responses(
					"error","Kandidat belum melakukan tes."
				));
			}

			return response()->json(new Responses(
				"success","Data berhasil dimuat", $DB->ID
			));
		}

		public function viewResult(Request $request): JsonResponse {
			$id = $request->input('id');
			$DB = DB::table('clyfar_test')->where('ID', $id)->first();
			$data = [];
		
			if ($DB) {
				if (!empty($DB->PAPI)) {
					$interpretPapikostick = $this->interpretPapikostick(json_decode($DB->PAPI));
					$data['PAPI'] = implode(', ', array_column($interpretPapikostick, 'NILAI'));
					$data['PAPI_TABLE'] = $interpretPapikostick;
				}
		
				if (!empty($DB->DISC)) {
					$data['DISC'] = $this->interpretDISC(json_decode($DB->DISC, true));
				}
		
				if (!empty($DB->KRAEPLIN)) {
					$data['KRAEPLIN'] = $this->interpretKraeplin(json_decode($DB->KRAEPLIN, true));
				}
		
				if (!empty($DB->MSDT)) {
					$data['MSDT'] = $this->interpretMSDT(json_decode($DB->MSDT, true));
				}

				if(!empty($DB->MBTI)) {
					$data['MBTI'] = json_decode($DB->MBTI, true);
				}

				if(!empty($DB->BAUM)) {
					$data['BAUM'] = $DB->BAUM;
				}

				if(!empty($DB->RMIB)) {
					$data['RMIB'] = $this->interpretRMIB(json_decode($DB->RMIB));
				}

				if(!empty($DB->CFIT)) {
					$data['CFIT'] = $this->interpretCFIT(json_decode($DB->CFIT, true));
				}

			}
		
			return response()->json(new Responses(
				"success", "Data berhasil dimuat", $data
			));
		}

		private function interpretCFIT($rawCFIT){
			$result = [
				"test1" => array_column($rawCFIT['sortFirst'], 'values'),
				"test2" => array_column($rawCFIT['sortSecond'], 'values'),
				"test3" => array_column($rawCFIT['sortThird'], 'values'),
				"test4" => array_column($rawCFIT['sortFourth'], 'values')
			];

			$data = Strings::CFIT;

			$test1 = $result['test1'];
			$test2 = $result['test2'];
			$test3 = $result['test3'];
			$test4 = $result['test4'];

			$skor = 0;
			
			$kunci1 = $data['kunci1'];
			$kunci2 = $data['kunci2'];
			$kunci3 = $data['kunci3'];
			$kunci4 = $data['kunci4'];
			$norma  = $data['norma'];
		
			for($i = 0 ; $i < count($kunci1); $i++ ){
				if(isset($test1[$i]) && strtoupper($kunci1[$i]) == $test1[$i]){ 
					$skor++;
				} else {
					$skor += 0;
				}
			}
			
			for($i = 0 ; $i < count($kunci2); $i++ ){
				if(isset($test2[$i]) && (strtoupper($kunci2[$i]) == $test2[$i] || strtoupper($kunci2[$i]) == strrev($test2[$i]))){ 
					$skor++;
				} else if (!isset($test2[$i])) {
					$skor += 0;
				}
			}
			
			for($i = 0 ; $i < count($kunci3); $i++ ){
				if(isset($test3[$i]) && strtoupper($kunci3[$i]) == $test3[$i]){ 
					$skor++;
				} else if (!isset($test3[$i])) {
					$skor += 0;
				}
			}
			
			for($i = 0 ; $i < count($kunci4); $i++ ){
				if(isset($test4[$i]) && strtoupper($kunci4[$i]) == $test4[$i]){ 
					$skor++;
				} else if (!isset($test4[$i])) {
					$skor += 0;
				}
			}

			if($skor == 0){

			$resultScore    = 38;
			$resultStatus   = 'Moderate Mental Retardation';

			} else {

			if($norma[$skor-1] >= 170){
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Genius';
			} else if ($norma[$skor-1] >= 140 && $norma[$skor-1] <= 169 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Very Superior';
			} else if ($norma[$skor-1] >= 120 && $norma[$skor-1] <= 139 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Superior';
			}else if ($norma[$skor-1] >= 110 && $norma[$skor-1] <= 119 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'High Average';
			}else if ($norma[$skor-1] >= 90 && $norma[$skor-1] <= 109 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Average';
			}else if ($norma[$skor-1] >= 80 && $norma[$skor-1] <= 89 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Low Average';
			} else if ($norma[$skor-1] >= 68 && $norma[$skor-1] <= 79 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Borderline Mental Retardation';
			} else if ($norma[$skor-1] >= 52 && $norma[$skor-1] <= 67 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Mild Mental Retardation';
			} else if ($norma[$skor-1] >= 36 && $norma[$skor-1] <= 51 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Moderate Mental Retardation';
			} else if ($norma[$skor-1] >= 20 && $norma[$skor-1] <= 35 ) {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Severe Mental Retardation';
			}else {
				$resultScore    = $norma[$skor-1];
				$resultStatus   = 'Profound Mental Retardation';
			}

			}


			return $hasilCFIT = [
				'score'       => $skor-1,
				'resultScore' => $resultScore,
				'resultStatus' => $resultStatus
			];

		}

		private function interpretRMIB($rawRMIB){
			$out    = [$rawRMIB->A[0],$rawRMIB->B[0],$rawRMIB->C[0],$rawRMIB->D[0],$rawRMIB->E[0],$rawRMIB->F[0],$rawRMIB->G[0],$rawRMIB->H[0],$rawRMIB->I[0],];
			$me     = [$rawRMIB->A[1],$rawRMIB->B[1],$rawRMIB->C[1],$rawRMIB->D[1],$rawRMIB->E[1],$rawRMIB->F[1],$rawRMIB->G[1],$rawRMIB->H[1],$rawRMIB->I[1],];
			$comp   = [$rawRMIB->A[2],$rawRMIB->B[2],$rawRMIB->C[2],$rawRMIB->D[2],$rawRMIB->E[2],$rawRMIB->F[2],$rawRMIB->G[2],$rawRMIB->H[2],$rawRMIB->I[2],];
			$sci    = [$rawRMIB->A[3],$rawRMIB->B[3],$rawRMIB->C[3],$rawRMIB->D[3],$rawRMIB->E[3],$rawRMIB->F[3],$rawRMIB->G[3],$rawRMIB->H[3],$rawRMIB->I[3],];
			$prs    = [$rawRMIB->A[4],$rawRMIB->B[4],$rawRMIB->C[4],$rawRMIB->D[4],$rawRMIB->E[4],$rawRMIB->F[4],$rawRMIB->G[4],$rawRMIB->H[4],$rawRMIB->I[4],];
			$aesth  = [$rawRMIB->A[5],$rawRMIB->B[5],$rawRMIB->C[5],$rawRMIB->D[5],$rawRMIB->E[5],$rawRMIB->F[5],$rawRMIB->G[5],$rawRMIB->H[5],$rawRMIB->I[5],];
			$lit    = [$rawRMIB->A[6],$rawRMIB->B[6],$rawRMIB->C[6],$rawRMIB->D[6],$rawRMIB->E[6],$rawRMIB->F[6],$rawRMIB->G[6],$rawRMIB->H[6],$rawRMIB->I[6],];
			$mus    = [$rawRMIB->A[7],$rawRMIB->B[7],$rawRMIB->C[7],$rawRMIB->D[7],$rawRMIB->E[7],$rawRMIB->F[7],$rawRMIB->G[7],$rawRMIB->H[7],$rawRMIB->I[7],];
			$ss     = [$rawRMIB->A[8],$rawRMIB->B[8],$rawRMIB->C[8],$rawRMIB->D[8],$rawRMIB->E[8],$rawRMIB->F[8],$rawRMIB->G[8],$rawRMIB->H[8],$rawRMIB->I[8],];
			$cler   = [$rawRMIB->A[9],$rawRMIB->B[9],$rawRMIB->C[9],$rawRMIB->D[9],$rawRMIB->E[9],$rawRMIB->F[9],$rawRMIB->G[9],$rawRMIB->H[9],$rawRMIB->I[9],];
			$prac   = [$rawRMIB->A[10],$rawRMIB->B[10],$rawRMIB->C[10],$rawRMIB->D[10],$rawRMIB->E[10],$rawRMIB->F[10],$rawRMIB->G[10],$rawRMIB->H[10],$rawRMIB->I[10],];
			$med    = [$rawRMIB->A[11],$rawRMIB->B[11],$rawRMIB->C[11],$rawRMIB->D[11],$rawRMIB->E[11],$rawRMIB->F[11],$rawRMIB->G[11],$rawRMIB->H[11],$rawRMIB->I[11],];

			$finalRMIB = [
					"out"   => $out,
					"me"    => $me,
					"comp"  => $comp,
					"sci"   => $sci,
					"prs"   => $prs,
					"aesth" => $aesth,
					"lit"   => $lit,
					"mus"   => $mus,
					"ss"    => $ss,
					"cler"  => $cler,
					"prac"  => $prac,
					"med"   => $med,
				"VALUE" => [
					"out"   =>  array_sum($out),
					"me"    =>  array_sum($me),
					"comp"  =>  array_sum($comp),
					"sci"   =>  array_sum($sci),
					"prs"   =>  array_sum($prs),
					"aesth" =>  array_sum($aesth),
					"lit"   =>  array_sum($lit),
					"mus"   =>  array_sum($mus),
					"ss"    =>  array_sum($ss),
					"cler"  =>  array_sum($cler),
					"prac"  =>  array_sum($prac),
					"med"   =>  array_sum($med),
				],
				"SORTED_VALUE"  =>  [
					"out"   =>  array_sum($out),
					"me"    =>  array_sum($me),
					"comp"  =>  array_sum($comp),
					"sci"   =>  array_sum($sci),
					"prs"   =>  array_sum($prs),
					"aesth" =>  array_sum($aesth),
					"lit"   =>  array_sum($lit),
					"mus"   =>  array_sum($mus),
					"ss"    =>  array_sum($ss),
					"cler"  =>  array_sum($cler),
					"prac"  =>  array_sum($prac),
					"med"   =>  array_sum($med),
				],
			];

			arsort($finalRMIB['SORTED_VALUE']);

			$sortedKeys = array_keys($finalRMIB['SORTED_VALUE']);
			$sortedValues = array_values($finalRMIB['SORTED_VALUE']);

			$interpretRMIB = Strings::RMIB;

			$finalRMIB['DESC'] = [
				[
					"Key"   	=> $sortedKeys[0],
					"Value" 	=> $sortedValues[0],
					"PRIA"		=> $interpretRMIB[$sortedKeys[0]]['Pria'],
					"WANITA"	=> $interpretRMIB[$sortedKeys[0]]['Wanita'],
					"DESC" 		=> $interpretRMIB[$sortedKeys[0]]['DESC']
				],
				[
					"Key"   	=> $sortedKeys[1],
					"Value" 	=> $sortedValues[1],
					"PRIA"		=> $interpretRMIB[$sortedKeys[1]]['Pria'],
					"WANITA"	=> $interpretRMIB[$sortedKeys[1]]['Wanita'],
					"DESC" 		=> $interpretRMIB[$sortedKeys[1]]['DESC']
				],
				[
					"Key"   	=> $sortedKeys[1],
					"Value" 	=> $sortedValues[1],
					"PRIA"		=> $interpretRMIB[$sortedKeys[1]]['Pria'],
					"WANITA"	=> $interpretRMIB[$sortedKeys[1]]['Wanita'],
					"DESC" 		=> $interpretRMIB[$sortedKeys[1]]['DESC']
				],
			];

		return $finalRMIB;
		}

		public function interpretMSDT($rawMSDT){
			$interpretMSDT = Strings::MSDT;

			//Perhitungan MSDT
			$total   = 0;

			$A       = array_fill(0, 8, 0); // Initialize $A with zeros
			$B       = array_fill(0, 8, 0); // Initialize $B with zeros
			$jumlah  = array_fill(0, 8, 0);
			$koreksi = [1,2,1,0,3,-1,0,-4];

			//Membuat Matrix Dari Input Jawaban
			for($baris = 0 ; $baris < 8; $baris++){
				for($kolom = 0 ; $kolom < 8; $kolom++){
					$jawaban[$baris][$kolom] = $rawMSDT[$total];
					if($rawMSDT[$total] == 'A'){
						$A[$baris] += 1;
					} else {
						$B[$kolom] += 1;
					}
					$total++;
				}
			}

			//Menyimpan Data Hasil Penjumlahan Variabel A, B, dan Koreksi 
			for ($i=0; $i < 8 ; $i++) { 
				$jumlah[$i] += $A[$i] + $B[$i] + $koreksi[$i];
			}

			$O  = 0;
			$E  = 0;
			$RO = 0;
			$TO = 0;

			//Menghitung Total Skor TO,RO,E,O
			foreach($jumlah as $key => $skor){
				switch ($key) {
					case 0:
						$O += $skor;
						break;
					case 1:
						$RO += $skor;
						break;
					case 2:
						$TO += $skor;
						break;
					case 3:
						$TO += $skor;
						$RO += $skor;
						break;
					case 4:
						$E += $skor;
						break;
					case 5:
						$RO += $skor;
						$E  += $skor;
						break;
					case 6:
						$TO += $skor;
						$E  += $skor;
						break;
					case 7:
						$TO += $skor;
						$RO += $skor;
						$E  += $skor;
						break;

					default:
						
						break;
				}
			}

			$indexMSDT= [
				"0" => range(0, 29),
				"0.6" => range(30, 31),
				"1.2" => range(32,32),
				"1.8" => range(33,33),
				"2.4" => range(34,34),
				"3.0" => range(35,35),
				"3.6" => range(36,37),
				"4.0" => range(38,100)
			];

			foreach($indexMSDT as $key => $indexMSDT){
				if(in_array($TO,$indexMSDT)){
					$konversiTO = floatval( $key );
				}
				if(in_array($RO,$indexMSDT)){
					$konversiRO = floatval( $key );
				}
				if(in_array($E,$indexMSDT)){
					$konversiE = floatval( $key );
				}
			}

			//Interpetasi hasil konversi TO,RO,E
			if($konversiTO > 2){
				if($konversiRO > 2){
					if($konversiE > 2){
						$hasil = "Executive";
						$deskripsi = $interpretMSDT["Executive"];
					} else {
						$hasil = "Compromiser";
						$deskripsi = $interpretMSDT["Compromiser"];
					}
				}else{
					if($konversiE > 2){
						$hasil = "Benevolent Autocrat";
						$deskripsi = $interpretMSDT["Benevolent Autocrat"];
					} else {
						$hasil = "Autocrat";
						$deskripsi = $interpretMSDT["Autocrat"];
					}
				}
			} else {
					if($konversiRO > 2){
						if($konversiE > 2){
							$hasil = "Developer";
							$deskripsi = $interpretMSDT["Developer"];
						} else {
							$hasil = "Missionary";
							$deskripsi = $interpretMSDT["Missionary"];
							
						}
					}else{
						if($konversiE > 2){
							$hasil = "Bureaucrat";
							$deskripsi = $interpretMSDT["Bureaucrat"];
							
						} else {
							$hasil = "Deserter";
							$deskripsi = $interpretMSDT["Deserter"];
							
						}
					}
				}

				$line1 = ['Ds','Mi','Au','Co','Bu','Dv','Ba','E'];
				$line2 = ['A','B','C','D','E','F','G','H'];

				return [
					'rawjawaban'       => $rawMSDT,
					'A'                => $A,
					'B'                => $B,
					'koreksi'          => $koreksi,
					'jumlah'           => $jumlah,
					'O'                => $O,
					'E'                => $E,
					'RO'               => $RO,
					'TO'               => $TO,
					'konversiE'        => $konversiE,
					'konversiRO'       => $konversiRO,
					'konversiTO'       => $konversiTO,
					'line1'            => $line1,
					'line2'            => $line2,
					'resultMSDT'       => $hasil,
					'deskripsi'        => $deskripsi
				];

		}

		private function interpretKraeplin($rawKraeplin){
			$kunciJawaban = Strings::KRAEPLIN;

			foreach($rawKraeplin as $data){
				$jumlahJawaban[] = [
					"JAWABAN"  => array_filter($data,'strlen'),
					"JUMLAH"   => count(array_filter($data,'strlen')),
					"MAX"      => empty(array_filter($data,'strlen')) ? 0 : max(array_filter($data,'strlen')),
					"MIN"      => empty(array_filter($data,'strlen')) ? 0 : min(array_filter($data,'strlen'))
				]; // Jumlah jawaban yang dari no 1 - 50
			}
			
			$nilaiTertinggi = max(array_column($jumlahJawaban,'JUMLAH'));         // Memuat nilai ter- dari no 1-50
			$nilaiTerendah  = min(array_column($jumlahJawaban,'JUMLAH'));         // Memuat nilai ter- dari no 1-50
			$nilaiSemua     = array_sum(array_column($jumlahJawaban,'JUMLAH'));   // Memuat nilai semua jawaban
			
			
			/* Proses pencocokan jawaban dengan kunci jawaban */
			for($i = 0; $i < count($kunciJawaban);$i++){
				for($subNilai = 0 ; $subNilai < 27; $subNilai++){

					$keyAnswer = isset($rawKraeplin[$i][$subNilai]) ? $rawKraeplin[$i][$subNilai] : 0;

					if($keyAnswer == $kunciJawaban[$i][$subNilai]){
						$tipeJawaban[$i][$subNilai] = "BENAR";
					} else if($keyAnswer == NULL){
						$tipeJawaban[$i][$subNilai] = "TIDAK DIISI";
					} else if($keyAnswer != $kunciJawaban[$i][$subNilai]){
						$tipeJawaban[$i][$subNilai] = "SALAH";
					} else {
						$tipeJawaban[$i][$subNilai] = "TIDAK DIISI";
					}
				}
			}
			
			$indeksAwal = 0;
			
			foreach($tipeJawaban as $data){
				for($subNilai = 0 ; $subNilai < 27 ; $subNilai++){
					if($data[$subNilai] == "BENAR"){
						$dataBenar[] = $data[$subNilai];
						if($indeksAwal < 16){
							$tahapKebenaran[] = "BENAR TAHAP 1";
						} else if($indeksAwal >= 16 && $indeksAwal <= 35 ){
							$tahapKebenaran[] = "BENAR TAHAP 2";
						} else if($indeksAwal >= 35 && $indeksAwal <= 50){
							$tahapKebenaran[] = "BENAR TAHAP 3";
						}
					} else if($data[$subNilai] == "SALAH"){
						$dataSalah[] = $data[$subNilai];
						if($indeksAwal < 16){
							$tahapKesalahan[] = "SALAH TAHAP 1";
						} else if($indeksAwal >= 16 && $indeksAwal <= 34 ){
							$tahapKesalahan[] = "SALAH TAHAP 2";
						} else if($indeksAwal >= 34 && $indeksAwal <= 50){
							$tahapKesalahan[] = "SALAH TAHAP 3";
						}
					} else if($data[$subNilai] == "TIDAK DIISI"){
						$dataTidakDiisi[] = $data[$subNilai];
					}
					$semuaJawaban[] = $data[$subNilai];
				}
				$indeksAwal++;
			}
			
			if(!isset($dataSalah)){
				$dataSalahProcessed = 0;
				$tahapKesalahan = [
					"SALAH TAHAP 1" => 0,
					"SALAH TAHAP 2" => 0,
					"SALAH TAHAP 3" => 0,
				];
			} else {
				$dataSalahProcessed = count($dataSalah);
				$tahapKesalahan     = $tahapKesalahan;
			}
			
			$jawabanBenar = count($dataBenar);
			$jawabanSalah = $dataSalahProcessed;
			$totalJawaban = $jawabanBenar + $jawabanSalah;
			
			/*
			| Mulai mendapatkan nilai untuk FASE
			*/
			
			$klasifikasiFaseBenar = array_count_values($tahapKebenaran);
			$klasifikasiFaseSalah = array_count_values($tahapKesalahan);
			
			/* Mendapatkan nilai Fase dengan perulangan */
			foreach($tipeJawaban as $key => $val){
				foreach($val as $insideVal){
					if($key < 16 ){
						if($insideVal != "TIDAK DIISI"){
							$tahapSatuJumlah[] = "TERJAWAB";
						}
					} else if( $key >= 16 && $key <= 34 ){
						if($insideVal != "TIDAK DIISI"){
							$tahapDuaJumlah[] = "TERJAWAB";
						}
					} else if( $key >= 34 && $key < 50 ){
						if($insideVal != "TIDAK DIISI"){
							$tahapTigaJumlah[] = "TERJAWAB";
						}
					}
				}
			}
			
			/* Mendapatkan nilai JANKER dengan limit per tahap */
			foreach($jumlahJawaban as $key => $value){
				if($key < 16 ){
					$nilaiMaksimumMinimumTahapSatu[] = $value['JUMLAH'];
				} else if( $key >= 16 && $key <= 34 ){
					$nilaiMaksimumMinimumTahapDua[] = $value['JUMLAH'];
				} else if( $key >= 34 && $key < 50 ){
					$nilaiMaksimumMinimumTahapTiga[] = $value['JUMLAH'];
				}
				$graphKraeplin[] = $value['JUMLAH'];
			}
			
			return $finalKraeplin = [
				"MEAN"            => $totalJawaban / 50,
				"RANGE"           => $nilaiTertinggi - $nilaiTerendah ,
				"AV_DEVIATION"    => $totalJawaban / (50-0),
				"SUM_OF_ERROR"    => $jawabanSalah,
				"SUM_OF_SKIPPED"  => 0,
				"SUM_OF_RIGHT"    => $jawabanBenar,
				"SUM_OF_ANSWER"   => $totalJawaban,
				"MIN"             => $nilaiTerendah,
				"MAX"             => $nilaiTertinggi,
				"SUM_OF_TEST"     => 50,
				"FASE"    => [
				"FASE_1"  =>  empty($klasifikasiFaseSalah['SALAH TAHAP 1']) ? 0 : round( (float) $klasifikasiFaseSalah['SALAH TAHAP 1'] / count($tahapSatuJumlah) * 10,3),
				"FASE_2"  =>  empty($klasifikasiFaseSalah['SALAH TAHAP 2']) ? 0 : round( (float) $klasifikasiFaseSalah['SALAH TAHAP 2'] / count($tahapDuaJumlah) * 10,3),
				"FASE_3"  =>  empty($klasifikasiFaseSalah['SALAH TAHAP 3']) ? 0 : round( (float) $klasifikasiFaseSalah['SALAH TAHAP 3'] / count($tahapTigaJumlah) * 10,3),
				"SEMUA"   =>  round( (float) $jawabanSalah / $totalJawaban ,3),
				],
				"PANKER"  =>  [
					"NILAI"   =>  $totalJawaban / 50,
					"FASE_1"  =>  count($tahapSatuJumlah) / 16,
					"FASE_2"  =>  count($tahapDuaJumlah) / 16,
					"FASE_3"  =>  count($tahapTigaJumlah) / 16,
				],
				"TINKER"  =>  [
					"NILAI"   =>  round( (float) $jawabanSalah / $totalJawaban ,3),
					"FASE_1"  =>  empty($klasifikasiFaseSalah['SALAH TAHAP 1']) ? 0 : round( (float) $klasifikasiFaseSalah['SALAH TAHAP 1'] / count($tahapSatuJumlah) * 10,3) ,
					"FASE_2"  =>  empty($klasifikasiFaseSalah['SALAH TAHAP 2']) ? 0 : round( (float) $klasifikasiFaseSalah['SALAH TAHAP 2'] / count($tahapDuaJumlah) * 10,3) ,
					"FASE_3"  =>  empty($klasifikasiFaseSalah['SALAH TAHAP 3']) ? 0 : round( (float) $klasifikasiFaseSalah['SALAH TAHAP 3'] / count($tahapTigaJumlah) * 10,3) ,
				],
				"JANKER"  =>  [
					"NILAI"   => $nilaiTertinggi - $nilaiTerendah ,
					"FASE_1"  => max($nilaiMaksimumMinimumTahapSatu) - min($nilaiMaksimumMinimumTahapSatu) ,
					"FASE_2"  => max($nilaiMaksimumMinimumTahapDua) - min($nilaiMaksimumMinimumTahapDua) ,
					"FASE_3"  => max($nilaiMaksimumMinimumTahapTiga) - min($nilaiMaksimumMinimumTahapTiga) ,
				],
				"GRAPH"     => $graphKraeplin , 
			];
		}

		private function interpretDISC($rawDISC){
			$soalDISC = Strings::DISC;

			for($i = 0; $i < 24; $i++){
				$varMost =  $rawDISC[$i]['Most'];
				$rawMost["M". $i + 1] = $varMost;

				$varLeast =  $rawDISC[$i]['Least'];
				$rawLeast["L". $i + 1] = $varLeast;
			}


			// Loop agar matching dengan kunci jawaban
			$i = 1;
			foreach($soalDISC['RAW_MOST'] as $data){

				if($rawMost['M'.$i] == $data['D']){
					$olahMost[] = "D";
				} else if($rawMost['M'.$i] == $data['I']){
					$olahMost[] = "I";
				} elseif($rawMost['M'.$i] == $data['S']){
					$olahMost[] = "S";
				} else if($rawMost['M'.$i] == $data['C']){
					$olahMost[] = "C";
				} else {
					$olahMost[] = "*";
				}
				
				$i++;

			}

			$i = 1;
			foreach($soalDISC['RAW_LEAST'] as $data){

				if($rawLeast['L'.$i] == $data['D']){
					$olahLeast[] = "D";
				} else if($rawLeast['L'.$i] == $data['I']){
					$olahLeast[] = "I";
				} elseif($rawLeast['L'.$i] == $data['S']){
					$olahLeast[] = "S";
				} else if($rawLeast['L'.$i] == $data['C']){
					$olahLeast[] = "C";
				} else {
					$olahLeast[] = "*";
				}

				$i++;

			}


			foreach($olahMost as $data){
				if($data == "D"){
					$pureMostD[] = "D";
				} else if($data == "I"){
					$pureMostI[] = "I";
				} else if($data == "S"){
					$pureMostS[] = "S";
				} else if($data == "C"){
					$pureMostC[] = "C";
				}  else {
					$pureMostBlank[] = "*";
				}

			}

			foreach($olahLeast as $data){
				if($data == "D"){
					$pureLeastD[] = "D";
				} else if($data == "I"){
					$pureLeastI[] = "I";
				} else if($data == "S"){
					$pureLeastS[] = "S";
				} else if($data == "C"){
					$pureLeastC[] = "C";
				} else {
					$pureLeastBlank[] = "*";
				}
			}


			$countData = [
				"Most"  =>  [
					"D" =>  isset($pureMostD) ? count($pureMostD) : 0,
					"I" =>  isset($pureMostI) ? count($pureMostI) : 0,
					"S" =>  isset($pureMostS) ? count($pureMostS) : 0,
					"C" =>  isset($pureMostC) ? count($pureMostC) : 0,
				],
				"Least" =>  [
					"D" =>  isset($pureLeastD) ? count($pureLeastD) : 0,
					"I" =>  isset($pureLeastI) ? count($pureLeastI) : 0,
					"S" =>  isset($pureLeastS) ? count($pureLeastS) : 0,
					"C" =>  isset($pureLeastC) ? count($pureLeastC) : 0,
				],
				"Change"=>  [
					"D" => (isset($pureMostD) ? count($pureMostD) : 0) - (isset($pureLeastD) ? count($pureLeastD) : 0),
					"I" => (isset($pureMostI) ? count($pureMostI) : 0) - (isset($pureLeastI) ? count($pureLeastI) : 0),
					"S" => (isset($pureMostS) ? count($pureMostS) : 0) - (isset($pureLeastS) ? count($pureLeastS) : 0),
					"C" => (isset($pureMostC) ? count($pureMostC) : 0) - (isset($pureLeastC) ? count($pureLeastC) : 0),
				]
			];

			$positionMostD = [0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,21]; // 18
			$positionMostI = [0,1,2,3,4,5,6,7,8,9,11,19]; // 12
			$positionMostS = [0,1,2,3,4,5,6,7,8,9,10,12,14,20]; // 14
			$positionMostC = [0,1,2,3,4,5,6,7,8,9,11,13,17]; // 13

			$realPositionMostD = [5,8,10,13,16,17,19,21,22,24,26,27,28,30,31,35,36,37];
			$realPositionMostI = [6,9,13,17,22,26,27,31,32,34,36,37];
			$realPositionMostS = [6,9,11,16,18,21,22,25,26,28,30,32,35,37];
			$realPositionMostC = [5,9,11,16,22,24,26,31,32,33,35,36,37];


			for($i = 0;$i <count($positionMostD);$i++){
				if($countData['Most']['D'] == $positionMostD[$i]){
					$pureMostD = $realPositionMostD[$i];
					break;
				}
			}

			for($i = 0;$i <count($positionMostI);$i++){
				if($countData['Most']['I'] == $positionMostI[$i]){
					$pureMostI = $realPositionMostI[$i];
					break;
				}
			}

			for($i = 0;$i <count($positionMostS);$i++){
				if($countData['Most']['S'] == $positionMostS[$i]){
					$pureMostS = $realPositionMostS[$i];
					break;
				}
			}

			for($i = 0;$i <count($positionMostC);$i++){
				if($countData['Most']['C'] == $positionMostC[$i]){
					$pureMostC = $realPositionMostC[$i];
					break;
				}
			}

			$positionLeastD = [17,16,15,14,13,12,11,10,9,8,7,6,5,4,3,2,1,0]; // 18
			$positionLeastI = [13,12,11,10,9,8,7,6,5,4,3,2,1,0]; // 14
			$positionLeastS = [19,16,13,12,11,10,9,8,7,6,5,4,3,2,1,0]; // 16
			$positionLeastC = [16,15,13,12,11,10,9,8,7,6,5,4,3,2,1,0]; // 16

			$realPositionLeastD = [1,3,5,6,7,9,11,12,13,16,17,19,21,23,25,29,35,37];
			$realPositionLeastI = [1,2,3,5,7,9,11,14,19,21,25,28,33,36];
			$realPositionLeastS = [1,2,3,5,7,9,12,14,17,21,23,25,28,33,36,37];
			$realPositionLeastC = [1,2,5,6,7,11,13,17,19,21,23,25,28,32,36,37];

			for($i = 0;$i <count($positionLeastD);$i++){
				if($countData['Least']['D'] == $positionLeastD[$i]){
					$pureLeastD = $realPositionLeastD[$i];
					break;
				}
			}

			for($i = 0;$i <count($positionLeastI);$i++){
				if($countData['Least']['I'] == $positionLeastI[$i]){
					$pureLeastI = $realPositionLeastI[$i];
					break;
				}
			}

			for($i = 0;$i <count($positionLeastS);$i++){
				if($countData['Least']['S']== $positionLeastS[$i]){
					$pureLeastS = $realPositionLeastS[$i];
					break;
				}
			}

			for($i = 0;$i <count($positionLeastC);$i++){
				if($countData['Least']['C'] == $positionLeastC[$i]){
					$pureLeastC = $realPositionLeastC[$i];
					break;
				}
			}

			$positionChangeD = [-20,-16,-13,-12,-11,-10,-9,-7,-6,-4,-3,-2,0,1,3,5,7,8,9,10,12,13,14,15,18,21]; // 26
			$positionChangeI = [-16,-10,-9,-8,-7,-6,-5,-4,-3,-2,-1,0,1,2,3,4,5,6,7,8,10,18]; // 22
			$positionChangeS = [-16,-15,-10,-9,-8,-7,-6,-5,-4,-3,-2,-1,0,1,2,3,4,5,6,7,8,9,10,11,15,20]; // 26 
			$positionChangeC = [-22,-18,-15,-14,-13,-10,-9,-8,-7,-6,-5,-4,-3,-2,-1,0,1,2,3,4,5,6,10,17]; // 24

			$realPositionChangeD = [2,3,4,6,7,9,11,12,13,16,17,18,19,21,22,23,25,27,28,30,31,32,34,35,36,37];
			$realPositionChangeI = [2,3,5,6,8,9,11,12,15,16,19,21,22,23,26,28,29,31,32,35,36,37];
			$realPositionChangeS = [1,2,5,8,9,11,12,15,16,17,18,19,22,23,24,26,27,28,29,30,31,32,34,35,36,37];
			$realPositionChangeC = [1,2,3,4,5,6,8,9,11,12,13,18,19,21,22,23,26,28,29,32,34,35,36,37];

			for($i = 0;$i <count($positionChangeD);$i++){
				if($countData['Change']['D'] == $positionChangeD[$i]){
					$pureChangeD = $realPositionChangeD[$i];
					break;
				} else {
					$pureChangeD = NULL;
			}
			}

			for($i = 0;$i <count($positionChangeI);$i++){
				if($countData['Change']['I'] == $positionChangeI[$i]){
					$pureChangeI = $realPositionChangeI[$i];
					break;
				} else {
					$pureChangeI = NULL;
				}
			}

			for($i = 0;$i <count($positionChangeS);$i++){
				if($countData['Change']['S'] == $positionChangeS[$i]){
					$pureChangeS = $realPositionChangeS[$i];
					break;
				} else {
					$pureChangeS = NULL;
				}
			}

			for($i = 0;$i <count($positionChangeC);$i++){
				if($countData['Change']['C'] == $positionChangeC[$i]){
					$pureChangeC = $realPositionChangeC[$i];
					break;
				} else {
					$pureChangeC = NULL;
				}
			}

			return $finalDISC = [
				"Most"  =>  [
					"D" => is_array($pureMostD) ? count($pureMostD) : $pureMostD  ,
					"I" => is_array($pureMostI) ? count($pureMostI) : $pureMostI  ,
					"S" => is_array($pureMostS) ? count($pureMostS) : $pureMostS  ,
					"C" => is_array($pureMostC) ? count($pureMostC) : $pureMostC  ,
				],
				"Least" =>  [
					"D" => is_array($pureLeastD) ? count($pureLeastD) : $pureLeastD,
					"I" => is_array($pureLeastI) ? count($pureLeastI) : $pureLeastI,
					"S" => is_array($pureLeastS) ? count($pureLeastS) : $pureLeastS,
					"C" => is_array($pureLeastC) ? count($pureLeastC) : $pureLeastC,
				],
				"Change"=>  [
					"D" => is_array($pureChangeD) ? count($pureChangeD) : $pureChangeD ,
					"I" => is_array($pureChangeI) ? count($pureChangeI) : $pureChangeI ,
					"S" => is_array($pureChangeS) ? count($pureChangeS) : $pureChangeS ,
					"C" => is_array($pureChangeC) ? count($pureChangeC) : $pureChangeC ,
				]
			];
		}

		private function interpretPapikostick($rawPapi){
			$G = [
			$rawPapi[0]->value,
			$rawPapi[10]->value,
			$rawPapi[20]->value,
			$rawPapi[30]->value,
			$rawPapi[40]->value,
			$rawPapi[50]->value,
			$rawPapi[60]->value,
			$rawPapi[70]->value,
			$rawPapi[80]->value,
		];
		
		
		$A = [
			"A" =>  [
			$rawPapi[1]->value,
			],
			"B" =>  [
			$rawPapi[79]->value,
			$rawPapi[68]->value,
			$rawPapi[57]->value,
			$rawPapi[46]->value,
			$rawPapi[35]->value,
			$rawPapi[24]->value,
			$rawPapi[13]->value,
			$rawPapi[2]->value,
			]
		];
		
		$L = [
			"A" =>  [
			$rawPapi[11]->value,
			$rawPapi[21]->value,
			$rawPapi[31]->value,
			$rawPapi[41]->value,
			$rawPapi[51]->value,
			$rawPapi[61]->value,
			$rawPapi[71]->value,
			$rawPapi[81]->value,
			],
			"B" =>  [
			$rawPapi[80]->value,
			]
		];
		
		$P = [
			"A" =>  [
			$rawPapi[12]->value,
			$rawPapi[2]->value,
			],
			"B" =>  [
			$rawPapi[69]->value,
			$rawPapi[58]->value,
			$rawPapi[47]->value,
			$rawPapi[36]->value,
			$rawPapi[25]->value,
			$rawPapi[14]->value,
			$rawPapi[3]->value,
			]
		];
		
		$I = [
			"A" =>  [
			$rawPapi[22]->value,
			$rawPapi[32]->value,
			$rawPapi[42]->value,
			$rawPapi[52]->value,
			$rawPapi[62]->value,
			$rawPapi[72]->value,
			$rawPapi[82]->value,
			],
			"B" =>  [
			$rawPapi[70]->value,
			$rawPapi[81]->value,
			]
		];
		
		$T = [
			"A" =>  [
			$rawPapi[33]->value,
			$rawPapi[43]->value,
			$rawPapi[53]->value,
			$rawPapi[63]->value,
			$rawPapi[73]->value,
			$rawPapi[83]->value,
			],
			"B" =>  [
			$rawPapi[60]->value,
			$rawPapi[71]->value,
			$rawPapi[82]->value,
			]
		];
		
		$V = [
			"A" =>  [
			$rawPapi[44]->value,
			$rawPapi[54]->value,
			$rawPapi[64]->value,
			$rawPapi[74]->value,
			$rawPapi[84]->value,
			],
			"B" =>  [
			$rawPapi[50]->value,
			$rawPapi[61]->value,
			$rawPapi[72]->value,
			$rawPapi[83]->value,
			]
		];
		
		$X = [
			"A" =>  [
			$rawPapi[3]->value,
			$rawPapi[13]->value,
			$rawPapi[23]->value,
			],
			"B" =>  [
			$rawPapi[59]->value,
			$rawPapi[48]->value,
			$rawPapi[37]->value,
			$rawPapi[26]->value,
			$rawPapi[15]->value,
			$rawPapi[4]->value,
			]
		];
		
		$S = [
			"A" =>  [
			$rawPapi[55]->value,
			$rawPapi[65]->value,
			$rawPapi[75]->value,
			$rawPapi[85]->value,
			],
			"B" =>  [
			$rawPapi[40]->value,
			$rawPapi[51]->value,
			$rawPapi[62]->value,
			$rawPapi[73]->value,
			$rawPapi[84]->value,
			]
		];
		
		$B = [
			"A" =>  [
			$rawPapi[4]->value,
			$rawPapi[14]->value,
			$rawPapi[24]->value,
			$rawPapi[34]->value,
			],
			"B" =>  [
			$rawPapi[49]->value,
			$rawPapi[38]->value,
			$rawPapi[27]->value,
			$rawPapi[16]->value,
			$rawPapi[5]->value,
			]
		];
		
		$O = [
			"A" =>  [
			$rawPapi[5]->value,
			$rawPapi[15]->value,
			$rawPapi[25]->value,
			$rawPapi[35]->value,
			$rawPapi[45]->value,
			],
			"B" =>  [
			$rawPapi[39]->value,
			$rawPapi[28]->value,
			$rawPapi[17]->value,
			$rawPapi[6]->value,
			]
		];
		
		$R = [
			"A" =>  [
			$rawPapi[66]->value,
			$rawPapi[76]->value,
			$rawPapi[86]->value,
			],
			"B" =>  [
			$rawPapi[30]->value,
			$rawPapi[41]->value,
			$rawPapi[52]->value,
			$rawPapi[63]->value,
			$rawPapi[74]->value,
			$rawPapi[85]->value,
			]
			
			
		];
		
		$D = [
			"A" =>  [
			$rawPapi[77]->value,
			$rawPapi[87]->value,
			],
			"B" =>  [
			$rawPapi[20]->value,
			$rawPapi[31]->value,
			$rawPapi[42]->value,
			$rawPapi[53]->value,
			$rawPapi[64]->value,
			$rawPapi[75]->value,
			$rawPapi[86]->value,
			]
		];
		
		$C = [
			"A" =>  [
			$rawPapi[88]->value,
			],
			"B" =>  [
			$rawPapi[10]->value,
			$rawPapi[21]->value,
			$rawPapi[32]->value,
			$rawPapi[43]->value,
			$rawPapi[54]->value,
			$rawPapi[65]->value,
			$rawPapi[76]->value,
			$rawPapi[87]->value,
			]
			
		];
		
		$Z = [
			"A" =>  [
			$rawPapi[6]->value,
			$rawPapi[16]->value,
			$rawPapi[26]->value,
			$rawPapi[36]->value,
			$rawPapi[46]->value,
			$rawPapi[56]->value,
			],
			"B" =>  [
			$rawPapi[29]->value,
			$rawPapi[18]->value,
			$rawPapi[7]->value,
			]
		];
		
		$E = [
			"B" =>  [
			$rawPapi[0]->value,
			$rawPapi[11]->value,
			$rawPapi[22]->value,
			$rawPapi[33]->value,
			$rawPapi[44]->value,
			$rawPapi[55]->value,
			$rawPapi[66]->value,
			$rawPapi[77]->value,
			$rawPapi[88]->value,
			]
		];
		
		$K = [
			"A" =>  [
			$rawPapi[7]->value,
			$rawPapi[17]->value,
			$rawPapi[27]->value,
			$rawPapi[37]->value,
			$rawPapi[47]->value,
			$rawPapi[57]->value,
			$rawPapi[67]->value,
			],
			"B" =>  [
			$rawPapi[19]->value,
			$rawPapi[8]->value,
			]
		];
		
		$F = [
			"A" =>  [
			$rawPapi[8]->value,
			$rawPapi[18]->value,
			$rawPapi[28]->value,
			$rawPapi[38]->value,
			$rawPapi[48]->value,
			$rawPapi[58]->value,
			$rawPapi[68]->value,
			$rawPapi[78]->value,
			],
			"B" =>  [
			$rawPapi[9]->value,
			]
		];
		
		$W = [
			"A" =>  [
			$rawPapi[9]->value,
			$rawPapi[19]->value,
			$rawPapi[29]->value,
			$rawPapi[39]->value,
			$rawPapi[49]->value,
			$rawPapi[59]->value,
			$rawPapi[69]->value,
			$rawPapi[79]->value,
			$rawPapi[89]->value,
			]
		];
		
		$N = [
			"B" =>  [
			$rawPapi[1]->value,
			$rawPapi[12]->value,
			$rawPapi[23]->value,
			$rawPapi[34]->value,
			$rawPapi[45]->value,
			$rawPapi[56]->value,
			$rawPapi[67]->value,
			$rawPapi[78]->value,
			$rawPapi[89]->value,
			]
		];
		
		foreach($G as $data){
			if($data == "A"){
			$listDataG[] = $data;
			}
		}
		
		// Nilai A
		foreach($A['A'] as $data){
			if($data == "A"){
			$listDataA[] = $data;
			}
		}
		
		foreach($A['B'] as $data){
			if($data == "B"){
			$listDataA[] = $data;
			}
		}
		
		// Nilai L
		foreach($L['A'] as $data){
			if($data == "A"){
			$listDataL[] = $data;
			}
		}
		
		foreach($L['B'] as $data){
			if($data == "B"){
			$listDataL[] = $data;
			}
		}
		
		// Nilai P
		foreach($P['A'] as $data){
			if($data == "A"){
			$listDataP[] = $data;
			}
		}
		
		foreach($P['B'] as $data){
			if($data == "B"){
			$listDataP[] = $data;
			}
		}
		
		// Nilai I
		foreach($I['A'] as $data){
			if($data == "A"){
			$listDataI[] = $data;
			}
		}
		
		foreach($I['B'] as $data){
			if($data == "B"){
			$listDataI[] = $data;
			}
		}
		
		// Nilai T
		foreach($T['A'] as $data){
			if($data == "A"){
			$listDataT[] = $data;
			}
		}
		
		foreach($T['B'] as $data){
			if($data == "B"){
			$listDataT[] = $data;
			}
		}
		
		// Nilai V
		foreach($V['A'] as $data){
			if($data == "A"){
			$listDataV[] = $data;
			}
		}
		
		foreach($V['B'] as $data){
			if($data == "B"){
			$listDataV[] = $data;
			}
		}
		
		// Nilai X
		foreach($X['A'] as $data){
			if($data == "A"){
			$listDataX[] = $data;
			}
		}
		
		foreach($X['B'] as $data){
			if($data == "B"){
			$listDataX[] = $data;
			}
		}
		
		// Nilai S
		foreach($S['A'] as $data){
			if($data == "A"){
			$listDataS[] = $data;
			}
		}
		
		foreach($S['B'] as $data){
			if($data == "B"){
			$listDataS[] = $data;
			}
		}
		
		// Nilai B
		foreach($B['A'] as $data){
			if($data == "A"){
			$listDataB[] = $data;
			}
		}
		
		foreach($B['B'] as $data){
			if($data == "B"){
			$listDataB[] = $data;
			}
		}
		
		// Nilai O
		foreach($O['A'] as $data){
			if($data == "A"){
			$listDataO[] = $data;
			}
		}
		
		foreach($O['B'] as $data){
			if($data == "B"){
			$listDataO[] = $data;
			}
		}
		
		// Nilai R
		foreach($R['A'] as $data){
			if($data == "A"){
			$listDataR[] = $data;
			}
		}
		
		foreach($R['B'] as $data){
			if($data == "B"){
			$listDataR[] = $data;
			}
		}
		
		// Nilai D
		foreach($D['A'] as $data){
			if($data == "A"){
			$listDataD[] = $data;
			}
		}
		
		foreach($D['B'] as $data){
			if($data == "B"){
			$listDataD[] = $data;
			}
		}
		
		// dd($listDataR);
		
		
		// Nilai C
		foreach($C['A'] as $data){
			if($data == "A"){
			$listDataC[] = $data;
			}
		}
		
		foreach($C['B'] as $data){
			if($data == "B"){
			$listDataC[] = $data;
			}
		}
		
		// Nilai Z
		foreach($Z['A'] as $data){
			if($data == "A"){
			$listDataZ[] = $data;
			}
		}
		
		foreach($Z['B'] as $data){
			if($data == "B"){
			$listDataZ[] = $data;
			}
		}
		
		// Nilai E
		
		foreach($E['B'] as $data){
			if($data == "B"){
			$listDataE[] = $data;
			}
		}
		
		// Nilai K
		foreach($K['A'] as $data){
			if($data == "A"){
			$listDataK[] = $data;
			}
		}
		
		foreach($K['B'] as $data){
			if($data == "B"){
			$listDataK[] = $data;
			}
		}
		
		// Nilai F
		foreach($F['A'] as $data){
			if($data == "A"){
			$listDataF[] = $data;
			}
		}
		
		foreach($F['B'] as $data){
			if($data == "B"){
			$listDataF[] = $data;
			}
		}
		
		// Nilai W
		foreach($W['A'] as $data){
			if($data == "A"){
			$listDataW[] = $data;
			}
		}
		
		// Nilai A
		foreach($N['B'] as $data){
			if($data == "B"){
			$listDataN[] = $data;
			}
		}
		
		return $finalPapi = [
			"A" =>  [
			"NILAI" => isset($listDataA) ? count($listDataA) : 0 ,
			"Deskripsi" => "Work Direction" ,
			"color" => "success",
			],
			"N" =>  [
			"NILAI" => isset($listDataN) ? count($listDataN) : 0 ,
			"Deskripsi" => "Work Direction" ,
			"color" => "success",
			],
			"G" =>  [
			"NILAI" => isset($listDataG) ? count($listDataG) : 0 ,
			"Deskripsi" => "Work Direction" ,
			"color" => "success",
			],
		
			"C" =>  [
			"NILAI" => isset($listDataC) ? count($listDataC) : 0 ,
			"Deskripsi" => "Work Style" ,
			"color" => "primary",
			],
			"D" =>  [
			"NILAI" => isset($listDataD) ? count($listDataD) : 0 ,
			"Deskripsi" => "Work Style" ,
			"color" => "primary",
			],
			"R" =>  [
			"NILAI" => isset($listDataR) ? count($listDataR) : 0 ,
			"Deskripsi" => "Work Style" ,
			"color" => "primary",
			],
		
			"T" =>  [
			"NILAI" => isset($listDataT) ? count($listDataT) : 0 ,
			"Deskripsi" => "Activity" ,
			"color" => "info",
			],
			"V" =>  [
			"NILAI" => isset($listDataV) ? count($listDataV) : 0 ,
			"Deskripsi" => "Activity" ,
			"color" => "info",
			],
		
			"W" =>  [
			"NILAI" => isset($listDataW) ? count($listDataW) : 0 ,
			"Deskripsi" => "Followership" ,
			"color" => "danger",
			],
			"F" =>  [
			"NILAI" => isset($listDataF) ? count($listDataF) : 0 ,
			"Deskripsi" => "Followership" ,
			"color" => "danger",
			],
		
			"L" =>  [
			"NILAI" => isset($listDataL) ? count($listDataL) : 0 ,
			"Deskripsi" => "Leadership" ,
			"color" => "dark",
			],
			"P" =>  [
			"NILAI" => isset($listDataP) ? count($listDataP) : 0 ,
			"Deskripsi" => "Leadership" ,
			"color" => "dark",
			],
			"I" =>  [
			"NILAI" => isset($listDataI) ? count($listDataI) : 0 ,
			"Deskripsi" => "Leadership" ,
			"color" => "dark",
			],
			
			
			"S" =>  [
			"NILAI" => isset($listDataS) ? count($listDataS) : 0 ,
			"Deskripsi" => "Social Nature" ,
			"color" => "warning",
			],
			"B" =>  [
			"NILAI" => isset($listDataB) ? count($listDataB) : 0 ,
			"Deskripsi" => "Social Nature" ,
			"color" => "warning",
			],
			"O" =>  [
				"NILAI" => isset($listDataO) ? count($listDataO) : 0 ,
				"Deskripsi" => "Social Nature" ,
				"color" => "warning",
			],
			
			"X" =>  [
				"NILAI" => isset($listDataX) ? count($listDataX) : 0 ,
				"Deskripsi" => "Social Nature" ,
				"color" => "warning",
			],
		
			
			"E" =>  [
				"NILAI" => isset($listDataE) ? count($listDataE) : 0 ,
				"Deskripsi" => "Temperament" ,
				"color" => "secondary",
			],
			"K" =>  [
				"NILAI" => isset($listDataK) ? count($listDataK) : 0 ,
				"Deskripsi" => "Temperament" ,
				"color" => "secondary",
			],
			"Z" =>  [
				"NILAI" => isset($listDataZ) ? count($listDataZ) : 0 ,
				"Deskripsi" => "Temperament" ,
				"color" => "secondary",
			],
		
		];
		
		}

	}
