<?php

namespace App\Http\Controllers\Clyfar;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\DTO\General;
use App\DTO\Test;
use Log;

class Psychological extends Controller
{
    private $validTokens = [
        'DISC'      => 'Veritas',
        'PAPI'      => 'Lumos',
        'Kraepelin' => 'Reparo',
        'BAUM'      => 'Finite',
        'MBTI'      => 'Sonorus',
        'MSDT'      => 'Stupefy',
        'RMIB'      => 'Felfare',
        'CFIT'      => 'Fussilini'
    ];

    private $testFields = [
        'DISC'      => 'DISC',
        'PAPI'      => 'PAPI',
        'KRAEPLIN'  => 'KRAEPLIN',
        'MBTI'      => 'MBTI',
        'MSDT'      => 'MSDT',
        'RMIB'      => 'RMIB',
        'CFIT'      => 'CFIT'
    ];

    public function verifyToken(Request $request): JsonResponse
    {
        $userToken = $request->input('token');
        $testType  = $request->input('type');

        if (isset($this->validTokens[$testType]) && $userToken === $this->validTokens[$testType]) {
            return response()->json(new General("success", "Selamat mengerjakan"), 200);
        }

        return response()->json(new General("error", "Token anda tidak sesuai!"), 200);
    }

    public function postTest(Request $request): JsonResponse
    {
        $testType = $request->input('TIPE');
        $pin      = $request->input('localPIN');

        if (empty($pin)) {
            return response()->json(new General("error", "Sesi anda telah selesai"), 200);
        }

        if (!isset($this->testFields[$testType])) {
            return response()->json(new General("error", "Invalid test type"), 400);
        }

        $field = $this->testFields[$testType];

        return $this->updateTestResults($pin, [$field => json_encode($request->input('data'))], $testType);
    }

    public function postBaum(Request $request): JsonResponse
    {
        $pin = $request->input('localPIN');

        if (empty($pin)) {
            return response()->json(new General("error", "Sesi anda telah selesai"), 200);
        }

        if (!$request->hasFile('baum')) {
            return response()->json(new General("error", "Tidak ada file yang dilampirkan"));
        }

        $image     = $request->file('baum');
        $imageName = $image->hashName();
        $image->move(public_path('BAUM/'), $imageName);

        return $this->updateTestResults($pin, ['BAUM' => $imageName], 'BAUM');
    }

    private function updateTestResults(string $pin, array $updateData, string $testType): JsonResponse
    {
        DB::beginTransaction();

        try {
            DB::table('clyfar_test')->updateOrInsert(["KODE" => $pin], array_merge(["KODE" => $pin], $updateData));

            $testLeft   = DB::table('clyfar_profile')->where('TOKEN', $pin)->first(['LIST']);
            $listOfTest = json_decode($testLeft->LIST, true);

            $newTest = array_values(array_diff($listOfTest, [$testType]));

            DB::table('clyfar_profile')->where('TOKEN', $pin)->update([
                "LIST" => empty($newTest) ? null : json_encode($newTest),
                "ENABLE_TEST" => empty($newTest) ? "No" : "Yes",
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(new General("error", "Database error"), 500);
        }

        return response()->json(new Test("success", "Anda akan diarahkan ke subtes berikutnya", empty($newTest) ? 'CLEAR' : $newTest[0]));
    }
}
