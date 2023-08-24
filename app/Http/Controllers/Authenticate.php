<?php

namespace App\Http\Controllers;

use DB;
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
                $createToken    = $data->createToken('token')->plainTextToken;
                $cookie         = cookie('jwt',$createToken, 60 * 24); // Sehari token a
    
                $loginData = [
                    'status'    => 'Authenticated',
                    'message'   => 'Authorized',
                    'name'      => $data->name,
                    'privilege' => $data->privilege
                ];
                // Dont forget to write access log here!
                return response()->json($loginData,200)->withCookie($cookie);
            }
    
            return response()->json([
                'status'    => 'Unauthorized',
            ],401);       
        } catch (\Throwable $e){
            return response($e,200);
        }
    }

    public function generateAdmin(){
        User::create([
            'name'              => 'Esdelfron',
            'email'             => 'esdelfron@studio_bersih.id',
            'email_verified_at' => now(),
            'password'          => Hash::make('Forg1verSign'),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    public function whoAmI(){
        return Hash::make('ud84staff');
        return response()->json(Auth::user()->name,200);    
    }

}
