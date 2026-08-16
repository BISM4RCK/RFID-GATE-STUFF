<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if (!Schema::hasColumn('gate_reader_status','firmware_version')) Schema::table('gate_reader_status',fn(Blueprint $t)=>$t->string('firmware_version',64)->nullable());
  if (!Schema::hasColumn('gate_reader_status','ip_address')) Schema::table('gate_reader_status',fn(Blueprint $t)=>$t->string('ip_address',45)->nullable());
  if (!Schema::hasColumn('gate_reader_status','mqtt_status')) Schema::table('gate_reader_status',fn(Blueprint $t)=>$t->string('mqtt_status',20)->nullable());
 }
 public function down(): void {
  foreach (['firmware_version','ip_address','mqtt_status'] as $c) if(Schema::hasColumn('gate_reader_status',$c)) Schema::table('gate_reader_status',fn(Blueprint $t)=>$t->dropColumn($c));
 }
};
