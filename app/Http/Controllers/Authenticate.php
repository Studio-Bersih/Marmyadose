<?php

namespace App\Http\Controllers;

use DB;
use Log;
use Hash;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class Authenticate extends Controller
{
    public function logIn(Request $request){
        try {
            $credentials = $request->validate([
                'email'     =>  ['required','string'],
                'password'  =>  ['required']
            ]);
            if(Auth::attempt($credentials, TRUE)){
                $data = User::where('email', $request->input('email'))->first();

                if(empty($data)){
                    return response()->json([
                        "MESSAGE"   => "Data anda tidak ditemukan (201)"
                    ],200);
                }

                /*
                | A deactivated Kosada account must not get in, even with the right
                | password — otherwise "deactivate" would only hide someone from a
                | list. Scoped to Kosada so UD84's own login is untouched.
                */
                if($data->groups === 'Kosada' && ($data->STATUS ?? 'Aktif') !== 'Aktif'){
                    return response()->json([
                        'status'  => 'Unauthorized',
                        'message' => 'Akun ini sudah dinonaktifkan. Hubungi administrator koperasi.',
                    ],403);
                }

                $loginData = [
                    'status'    => 'Authenticated',
                    'message'   => 'Authorized',
                    'name'      => $data->name,
                    /*
                    | Returned so the frontend knows which account is signed in and
                    | which nav items to show. NOT authorisation: every account
                    | action re-verifies an administrator's password server-side —
                    | see Kosada\Akun::requireAdmin.
                    */
                    'email'     => $data->email,
                    'privilege' => $data->privilege
                ];
                // Dont forget to write access log here!
                return response()->json($loginData,200);
            }
    
            return response()->json([
                'status'    => 'Unauthorized',
                'message'   => 'Data anda tidak ditemukan'
            ],401);       
        } catch (\Throwable $e){
            Log::info($e);
            return response($e,200);
        }
    }

    public function generateAdmin(){
        User::create([
            'name'              => 'Ibu Heridawati',
            'email'             => 'admin@kosada.id',
            'email_verified_at' => now(),
            'password'          => Hash::make('koperasikosada'),
            // The column is `groups`, plural. This said `group`, which is not a
            // column, so mass assignment dropped it and the seeded account landed
            // with no app scope.
            'groups'            => 'Kosada',
            'privilege'         => 'Administrator',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }


    public function whoAmI(){
        return Hash::make('ud84staff');
        return response()->json(Auth::user()->name,200);    
    }

}
