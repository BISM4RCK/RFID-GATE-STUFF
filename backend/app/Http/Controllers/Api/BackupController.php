<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BackupController extends Controller
{
    private function superAdmin(Request $request)
    {
        $user = DB::table('users')->where('id', $request->session()->get('user_id'))->first();
        abort_unless($user && (int)$user->is_super_admin === 1 && $user->status === 'active', 403);
        return $user;
    }

    private function envArgs(): array
    {
        $cfg = config('database.connections.mysql');
        return [
            'host' => (string)($cfg['host'] ?? env('DB_HOST','mysql')),
            'port' => (string)($cfg['port'] ?? env('DB_PORT',3306)),
            'database' => (string)($cfg['database'] ?? env('DB_DATABASE','smart_gate')),
            'username' => (string)($cfg['username'] ?? env('DB_USERNAME','smart_gate')),
            'password' => (string)($cfg['password'] ?? env('DB_PASSWORD','')),
        ];
    }

    private function runCommand(array $command, ?string $input = null): array
    {
        $parts = array_map(fn($v) => escapeshellarg((string)$v), $command);
        $descriptor = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $env = ['MYSQL_PWD' => $this->envArgs()['password'], 'PATH' => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'];
        $proc = proc_open(implode(' ', $parts), $descriptor, $pipes, base_path(), $env);
        abort_unless(is_resource($proc), 500, 'Unable to start database utility.');
        if ($input !== null) fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exit = proc_close($proc);
        return [$exit, $stdout, $stderr];
    }

    public function export(Request $request)
    {
        $this->superAdmin($request);
        $c = $this->envArgs();
        [$exit, $stdout, $stderr] = $this->runCommand(['mysqldump','--single-transaction','--routines','--triggers','--skip-lock-tables','-h',$c['host'],'-P',$c['port'],'-u',$c['username'],$c['database']]);
        abort_unless($exit === 0, 500, 'Database backup failed: '.$stderr);
        return response($stdout, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="smart-gate-backup-'.now()->format('Ymd-His').'.sql"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function restore(Request $request)
    {
        $this->superAdmin($request);
        $request->validate(['backup' => ['required','file','max:102400','mimes:sql,txt']]);
        $c = $this->envArgs();
        $sql = file_get_contents($request->file('backup')->getRealPath());
        abort_unless(is_string($sql) && trim($sql) !== '', 422, 'Backup file is empty.');
        [$exit, , $stderr] = $this->runCommand(['mysql','-h',$c['host'],'-P',$c['port'],'-u',$c['username'],$c['database']], $sql);
        abort_unless($exit === 0, 422, 'Database restore failed: '.$stderr);
        return ['ok'=>true,'message'=>'Database restore completed successfully.'];
    }
}
