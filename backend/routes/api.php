<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GateController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\VisitorController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\RfidController;
Route::get('/health', fn()=>['ok'=>true,'service'=>'smart-gate','version'=>'32']);
Route::middleware('web')->group(function(){
    Route::post('/auth/login',[AuthController::class,'login']);
    Route::post('/auth/logout',[AuthController::class,'logout']);
    Route::get('/auth/me',[AuthController::class,'me']);
    Route::get('/dashboard',[AccountController::class,'dashboard']);
    Route::get('/vehicles',[VehicleController::class,'index']);
    Route::post('/vehicles',[VehicleController::class,'store']);
    Route::delete('/vehicles/{vehicle}',[VehicleController::class,'destroy']);
    Route::get('/rfid/events',[RfidController::class,'events']);
    Route::get('/gate/status',[GateController::class,'status']);
    Route::post('/gate/override',[GateController::class,'override']);
});
Route::get('/visitor/{credential}',[VisitorController::class,'status']);
Route::prefix('esp32')->group(function(){
    Route::post('/rfid/scan',[RfidController::class,'scan']);
    Route::get('/gate/commands',[GateController::class,'commands']);
    Route::post('/gate/commands/{command}/complete',[GateController::class,'complete']);
    Route::post('/heartbeat',[GateController::class,'heartbeat']);
});
