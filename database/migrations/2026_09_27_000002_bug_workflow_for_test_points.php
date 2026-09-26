<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Testing points become bugs with a workflow: Open → In progress → Ready for
 * test → Closed, or back round as Repeated. Existing points and their status
 * history are carried across so nothing shows an unknown status.
 */
return new class extends Migration
{
    private const FORWARD = [
        'to_test' => 'open',
        'testing' => 'ready_for_test',
        'passed' => 'closed',
        'failed' => 'repeated',
    ];

    // Lossy the other way: In progress and Ready for test both fold into "testing".
    private const BACKWARD = [
        'open' => 'to_test',
        'in_progress' => 'testing',
        'ready_for_test' => 'testing',
        'closed' => 'passed',
        'repeated' => 'failed',
    ];

    public function up(): void
    {
        $this->remap(self::FORWARD);

        Schema::table('test_points', function (Blueprint $table) {
            $table->string('status')->default('open')->change();
        });
    }

    public function down(): void
    {
        $this->remap(self::BACKWARD);

        Schema::table('test_points', function (Blueprint $table) {
            $table->string('status')->default('to_test')->change();
        });
    }

    /**
     * @param  array<string, string>  $map
     */
    private function remap(array $map): void
    {
        DB::transaction(function () use ($map) {
            foreach ($map as $from => $to) {
                DB::table('test_points')->where('status', $from)->update(['status' => $to]);

                $history = DB::table('status_changes')->where('subject_type', 'App\\Models\\TestPoint');
                (clone $history)->where('from_status', $from)->update(['from_status' => $to]);
                (clone $history)->where('to_status', $from)->update(['to_status' => $to]);
            }

            // Columns changed, so renumber each column's positions densely.
            $rows = DB::table('test_points')->orderBy('project_id')->orderBy('status')->orderBy('position')->orderBy('id')->get(['id', 'project_id', 'status']);
            $counters = [];

            foreach ($rows as $row) {
                $key = $row->project_id.'|'.$row->status;
                $counters[$key] = ($counters[$key] ?? -1) + 1;
                DB::table('test_points')->where('id', $row->id)->update(['position' => $counters[$key]]);
            }
        });
    }
};
