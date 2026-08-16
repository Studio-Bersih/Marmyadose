<?php

namespace App\Http\Controllers\Kosada\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\User;

/*
| Administrator-only actions.
|
| Kosada has no session, no token and no auth middleware (see
| Kosada-Auth-Proposal.md), so disabling a button in the frontend protects
| nothing — the URL still works and the API is still open.
|
| Any action restricted to administrators therefore re-asks for the acting
| administrator's own email and password, and this verifies BOTH that the
| credential is real and that the account is an active Administrator in the
| Kosada group. Someone without an administrator's password cannot perform the
| action however they call it.
|
| Extracted from Kosada\Akun once a third controller needed the same rule.
| Keeping one copy matters here more than usual: a divergent copy of an
| authorisation check is a hole that looks like a guard.
*/
trait RequiresAdmin
{
    private const ADMIN_GROUP = 'Kosada';

    /*
    | Returns a JSON error response when the caller is not an active Kosada
    | administrator, or null when they are.
    |
    | Usage:
    |     if($denied = $this->requireAdmin($request)) return $denied;
    */
    protected function requireAdmin(Request $request){
        $email    = $request->input('ADMIN_EMAIL');
        $password = $request->input('ADMIN_PASSWORD');

        if(empty($email) || empty($password)){
            return response()->json([
                'status'  => 'error',
                'message' => 'Konfirmasi email dan password administrator diperlukan',
            ],401);
        }

        // Auth::validate, not attempt: this only verifies the credential and must
        // not start a session.
        if(!Auth::validate(['email' => $email, 'password' => $password])){
            return response()->json([
                'status'  => 'error',
                'message' => 'Email atau password administrator tidak sesuai',
            ],401);
        }

        $admin = User::where('email',$email)->first();

        if(empty($admin) || $admin->groups !== self::ADMIN_GROUP){
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun ini bukan akun Kosada',
            ],403);
        }

        if($admin->privilege !== 'Administrator'){
            return response()->json([
                'status'  => 'error',
                'message' => 'Tindakan ini hanya untuk akun Administrator',
            ],403);
        }

        if(($admin->STATUS ?? 'Aktif') !== 'Aktif'){
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun administrator ini sudah nonaktif',
            ],403);
        }

        return null;
    }
}
