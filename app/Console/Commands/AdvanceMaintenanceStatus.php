<?php

namespace App\Console\Commands;

use App\Models\Maintenance;
use Illuminate\Console\Command;

class AdvanceMaintenanceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maintenance:advance-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move Scheduled maintenances whose date has arrived to On Progress';

    /**
     * Execute the console command: a Scheduled maintenance whose date is
     * today or in the past becomes On Progress. Done stays manual.
     */
    public function handle(): int
    {
        $advanced = Maintenance::query()
            ->where('status', Maintenance::STATUS_SCHEDULED)
            ->whereDate('maintenance_date', '<=', today())
            ->update(['status' => Maintenance::STATUS_IN_PROGRESS]);

        $this->info("Advanced {$advanced} maintenance record(s) to On Progress.");

        return self::SUCCESS;
    }
}
