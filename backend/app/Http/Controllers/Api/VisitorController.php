<?php
namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;
class VisitorController{public function status($credential){$row=DB::table('visitor_credentials')->where('credential',strtoupper($credential))->first();if(!$row)return response()->json(['ok'=>false,'status'=>'not_found'],404);$v=DB::table('visitor_requests')->where('id',$row->visitor_request_id)->first();return ['ok'=>true,'visitor_id'=>strtoupper($credential),'status'=>$v->status??'unknown'];}}
