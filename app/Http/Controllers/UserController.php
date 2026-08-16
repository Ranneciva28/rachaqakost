<?php
namespace App\Http\Controllers;
use App\Models\User;use Illuminate\Http\Request;use Illuminate\Validation\Rules\Password;
class UserController extends Controller {
 public function store(Request $r){$this->owner($r);$d=$r->validate(['name'=>['required','max:120'],'email'=>['required','email','max:150','unique:users,email'],'role'=>['required','in:OWNER,PENJAGA'],'password'=>['required','confirmed',Password::min(10)->letters()->numbers()]]);User::create($d);return back()->with('success','User berhasil ditambahkan.');}
 public function update(Request $r,User $user){$this->owner($r);$d=$r->validate(['name'=>['required','max:120'],'role'=>['required','in:OWNER,PENJAGA'],'password'=>['nullable','confirmed',Password::min(10)->letters()->numbers()]]);if($user->is($r->user())&&$d['role']!=='OWNER')return back()->withErrors(['role'=>'Owner aktif tidak bisa menurunkan role sendiri.']);if(blank($d['password']))unset($d['password']);$user->update($d);return back()->with('success','Akses user diperbarui.');}
 private function owner(Request $r):void{abort_unless($r->user()->isOwner(),403);}
}
