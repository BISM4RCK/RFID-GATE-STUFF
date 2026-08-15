<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;use App\Models\User;use Illuminate\Http\Request;use Illuminate\Support\Facades\Hash;
class AuthController{public function login(Request $r){$d=$r->validate(['email'=>'required|email','password'=>'required']);$u=User::where('email',$d['email'])->where('status','active')->first();if(!$u||!Hash::check($d['password'],$u->password))return response()->json(['ok'=>false,'message'=>'Invalid credentials.'],401);$r->session()->regenerate();$r->session()->put('user_id',$u->id);return ['ok'=>true,'user'=>$u];}public function logout(Request $r){$r->session()->invalidate();return ['ok'=>true];}public function me(Request $r){$u=User::find($r->session()->get('user_id'));return ['ok'=>true,'user'=>$u];}}
