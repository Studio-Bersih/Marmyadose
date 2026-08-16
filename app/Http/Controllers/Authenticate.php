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
    
                $loginData = [
                    'status'    => 'Authenticated',
                    'message'   => 'Authorized',
                    'name'      => $data->name,
                    // Returned so the frontend knows which account is signed in and
                    // can prefill the change-password form. It is NOT authorisation:
                    // changePassword still requires the current password.
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
            'group'             => 'Kosada',
            'privilege'         => 'Administrator',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    /*
    | Change the password of an existing account.
    |
    | The current password is required and verified with Auth::attempt, so this is
    | safe despite there being no session: knowing an email alone gets you nothing.
    |
    | Note for Kosada specifically: staff share a single account, so a change here
    | affects everyone. The frontend warns about that.
    */
    public function changePassword(Request $request){
        try {
            // Messages are spelled out because Laravel humanises the UPPER_SNAKE
            // field names into "p a s s w o r d  b a r u", which staff would see.
            $validated = $request->validate([
                'email'         => ['required','string','email'],
                'PASSWORD_LAMA' => ['required','string'],
                'PASSWORD_BARU' => ['required','string','min:8'],
            ],[
                'email.required'         => 'Email akun wajib diisi',
                'email.email'            => 'Format email tidak valid',
                'PASSWORD_LAMA.required' => 'Password lama wajib diisi',
                'PASSWORD_BARU.required' => 'Password baru wajib diisi',
                'PASSWORD_BARU.min'      => 'Password baru minimal 8 karakter',
            ]);

            $email      = $validated['email'];
            $lama       = $validated['PASSWORD_LAMA'];
            $baru       = $validated['PASSWORD_BARU'];

            if($lama === $baru){
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Password baru tidak boleh sama dengan password lama',
                ],422);
            }

            if(!Auth::validate(['email' => $email, 'password' => $lama])){
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Password lama tidak sesuai',
                ],401);
            }

            $user = User::where('email',$email)->first();
            if(empty($user)){
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Akun tidak ditemukan',
                ],404);
            }

            $user->password = Hash::make($baru);
            $user->save();

            return response()->json([
                'status'  => 'success',
                'message' => 'Password berhasil diubah!',
            ],200);

        } catch (\Illuminate\Validation\ValidationException $e){
            return response()->json([
                'status'  => 'error',
                'message' => collect($e->errors())->flatten()->first(),
            ],422);
        } catch (\Throwable $e){
            Log::info($e);
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan pada server',
            ],500);
        }
    }

    public function whoAmI(){
        return Hash::make('ud84staff');
        return response()->json(Auth::user()->name,200);    
    }

}
