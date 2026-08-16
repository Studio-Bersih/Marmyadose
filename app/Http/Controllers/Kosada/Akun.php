<?php

namespace App\Http\Controllers\Kosada;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Hash;

use App\Http\Controllers\Kosada\Concerns\RequiresAdmin;
use App\Models\User;

/*
| Kosada account management.
|
| Two roles, both already present in the schema as users.privilege:
|   Administrator — everything, including managing accounts and passwords
|   Staff         — everything except anything to do with passwords or accounts
|
| ENFORCEMENT NOTE — read before changing anything here.
|
| Kosada has no session, no token and no auth middleware (see
| Kosada-Auth-Proposal.md). Hiding a menu item in the frontend therefore protects
| nothing: the URL still works and the API is still open.
|
| So every mutating endpoint in this controller re-asks for the acting admin's
| own email and password, and requireAdmin() verifies BOTH that the credential is
| real and that the account is an Administrator in the Kosada group. A Staff
| member cannot create, edit, deactivate or delete an account even by calling the
| API directly, because they would need an administrator's password to do it.
|
| That is genuine enforcement for these actions. It is NOT a substitute for
| authenticating the rest of the app, which remains open and is tracked
| separately.
*/
class Akun extends Controller
{
    use RequiresAdmin;

    private const GROUP = 'Kosada';


    private function validateOrFail(Request $request, array $rules, array $messages){
        $validator = Validator::make($request->all(), $rules, $messages);

        if($validator->fails()){
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ],422);
        }

        return null;
    }

    /*
    | The account register.
    |
    | Scoped to groups = 'Kosada'. The users table is shared with UD84, and a
    | Kosada administrator has no business editing another product's logins.
    |
    | Passwords are never selected, let alone returned.
    */
    public function getAkun(Request $request){
        $data = User::where('groups', self::GROUP)
            ->orderBy('name')
            ->get(['id','name','email','privilege','STATUS','created_at'])
            ->map(function($row){
                return [
                    'ID'         => $row->id,
                    'NAMA'       => $row->name,
                    'EMAIL'      => $row->email,
                    'ROLE'       => $row->privilege,
                    'STATUS'     => $row->STATUS ?? 'Aktif',
                    'CREATED_AT' => $row->created_at
                        ? Carbon::parse($row->created_at)->translatedFormat('d F Y')
                        : '-',
                ];
            });

        return response()->json($data,200);
    }

    public function addAkun(Request $request){
        if($denied = $this->requireAdmin($request)) return $denied;

        $invalid = $this->validateOrFail($request,[
            'NAMA'     => ['required','string','max:255'],
            'EMAIL'    => ['required','email','max:255','unique:users,email'],
            'PASSWORD' => ['required','string','min:8'],
            'ROLE'     => ['required','in:Administrator,Staff'],
        ],[
            'NAMA.required'     => 'Nama wajib diisi',
            'EMAIL.required'    => 'Email wajib diisi',
            'EMAIL.email'       => 'Format email tidak valid',
            'EMAIL.unique'      => 'Email ini sudah dipakai akun lain',
            'PASSWORD.required' => 'Password wajib diisi',
            'PASSWORD.min'      => 'Password minimal 8 karakter',
            'ROLE.in'           => 'Role harus Administrator atau Staff',
        ]);
        if($invalid) return $invalid;

        User::create([
            'name'       => $request->input('NAMA'),
            'email'      => $request->input('EMAIL'),
            'password'   => Hash::make($request->input('PASSWORD')),
            'privilege'  => $request->input('ROLE'),
            'groups'     => self::GROUP,
            'STATUS'     => 'Aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun ' . $request->input('NAMA') . ' berhasil dibuat!',
        ],200);
    }

    /*
    | Edit name, email and role. Password is optional here — leaving it blank
    | keeps the existing one.
    |
    | An administrator resetting a staff password is what makes a forgotten
    | password recoverable. Staff cannot change passwords at all, so without this
    | a forgotten one would leave the account permanently unusable.
    */
    public function updateAkun(Request $request){
        if($denied = $this->requireAdmin($request)) return $denied;

        $invalid = $this->validateOrFail($request,[
            'ID'       => ['required','integer'],
            'NAMA'     => ['required','string','max:255'],
            'EMAIL'    => ['required','email','max:255'],
            'ROLE'     => ['required','in:Administrator,Staff'],
            'PASSWORD' => ['nullable','string','min:8'],
        ],[
            'NAMA.required'  => 'Nama wajib diisi',
            'EMAIL.required' => 'Email wajib diisi',
            'EMAIL.email'    => 'Format email tidak valid',
            'ROLE.in'        => 'Role harus Administrator atau Staff',
            'PASSWORD.min'   => 'Password minimal 8 karakter',
        ]);
        if($invalid) return $invalid;

        $akun = User::where('id',$request->input('ID'))->where('groups',self::GROUP)->first();

        if(empty($akun)){
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun tidak ditemukan!',
            ],404);
        }

        // The email is the login identifier, so a collision would lock someone out.
        $taken = User::where('email',$request->input('EMAIL'))
            ->where('id','!=',$akun->id)->exists();

        if($taken){
            return response()->json([
                'status'  => 'error',
                'message' => 'Email ini sudah dipakai akun lain',
            ],422);
        }

        if($guard = $this->guardLastAdmin($akun, $request->input('ROLE'), $akun->STATUS ?? 'Aktif')) return $guard;

        $akun->name      = $request->input('NAMA');
        $akun->email     = $request->input('EMAIL');
        $akun->privilege = $request->input('ROLE');

        if($request->filled('PASSWORD')){
            $akun->password = Hash::make($request->input('PASSWORD'));
        }

        $akun->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun ' . $akun->name . ' berhasil diperbarui!',
        ],200);
    }

    public function statusAkun(Request $request){
        if($denied = $this->requireAdmin($request)) return $denied;

        $invalid = $this->validateOrFail($request,[
            'ID'     => ['required','integer'],
            'STATUS' => ['required','in:Aktif,Nonaktif'],
        ],[
            'STATUS.in' => 'Status harus Aktif atau Nonaktif',
        ]);
        if($invalid) return $invalid;

        $akun = User::where('id',$request->input('ID'))->where('groups',self::GROUP)->first();

        if(empty($akun)){
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun tidak ditemukan!',
            ],404);
        }

        if($guard = $this->guardLastAdmin($akun, $akun->privilege, $request->input('STATUS'))) return $guard;

        $akun->STATUS = $request->input('STATUS');
        $akun->save();

        return response()->json([
            'status'  => 'success',
            'message' => $request->input('STATUS') === 'Aktif'
                ? 'Akun ' . $akun->name . ' diaktifkan kembali.'
                : 'Akun ' . $akun->name . ' dinonaktifkan.',
        ],200);
    }

    public function deleteAkun(Request $request){
        if($denied = $this->requireAdmin($request)) return $denied;

        $invalid = $this->validateOrFail($request,[
            'ID' => ['required','integer'],
        ],[
            'ID.required' => 'Akun wajib dipilih',
        ]);
        if($invalid) return $invalid;

        $akun = User::where('id',$request->input('ID'))->where('groups',self::GROUP)->first();

        if(empty($akun)){
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun tidak ditemukan!',
            ],404);
        }

        // Deleting the last active administrator would leave nobody able to manage
        // accounts or passwords, with no way back in through the app.
        if($guard = $this->guardLastAdmin($akun, 'Staff', 'Nonaktif')) return $guard;

        $nama = $akun->name;
        $akun->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun ' . $nama . ' berhasil dihapus.',
        ],200);
    }

    /*
    | Refuses any change that would remove the last active administrator.
    |
    | Without this, demoting or deactivating the wrong account locks the co-op out
    | of its own account management permanently — there is no other way to grant
    | the role back from inside the app.
    |
    | $nextRole / $nextStatus are what the account WOULD become.
    */
    private function guardLastAdmin(User $akun, string $nextRole, string $nextStatus){
        $wasActiveAdmin = $akun->privilege === 'Administrator'
                       && ($akun->STATUS ?? 'Aktif') === 'Aktif';

        $staysActiveAdmin = $nextRole === 'Administrator' && $nextStatus === 'Aktif';

        if(!$wasActiveAdmin || $staysActiveAdmin){
            return null;
        }

        $others = User::where('groups', self::GROUP)
            ->where('privilege','Administrator')
            ->where('STATUS','Aktif')
            ->where('id','!=',$akun->id)
            ->count();

        if($others > 0){
            return null;
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Ini satu-satunya Administrator yang aktif. Angkat administrator lain dulu sebelum mengubah akun ini.',
        ],422);
    }
}
