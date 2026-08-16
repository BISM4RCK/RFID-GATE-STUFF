<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
class CleanupExpiredGuests extends Command
{
    protected $signature = 'smart-gate:cleanup-guests';
    protected $description = 'Expire guest credentials whose stay period has ended.';
    public function handle(): int
    {
        $count=DB::table('visitor_requests as vr')->join('visitor_credentials as vc','vc.visitor_request_id','=','vr.id')
            ->whereNotNull('vc.expires_at')->where('vc.expires_at','<=',now())->whereIn('vr.status',['pending','approved'])
            ->update(['vr.status'=>'expired','vr.updated_at'=>now()]);
        $this->info("Expired {$count} guest credential(s).");
        return self::SUCCESS;
    }
}
