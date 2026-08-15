<?php
namespace App\Http\Controllers\Api;
use App\Models\User;use Illuminate\Http\Request;
class AccountController{public function dashboard(Request $r){$u=User::findOrFail($r->session()->get('user_id'));$f=['resident'=>['vehicles','visitors','requests','tickets'],'guard'=>['gate_override','walkin','blacklist','gate_logs','tickets'],'admin'=>['gate_override','vehicles','users','blacklist','gate_logs','admin_guard_logs','rfid','walkin','tickets','settings'],'super_admin'=>['gate_override','vehicles','users','blacklist','gate_logs','admin_guard_logs','rfid','walkin','tickets','settings']];return ['ok'=>true,'user'=>$u,'features'=>$f[$u->role]??[]];}}
