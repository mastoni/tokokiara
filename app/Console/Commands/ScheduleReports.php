<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ReportSchedulingService;
use Illuminate\Support\Facades\Log;

class ScheduleReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:schedule {type=daily : The type of report to schedule (daily, weekly, monthly)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedule and send automated reports';

    protected $reportSchedulingService;

    /**
     * Create a new command instance.
     */
    public function __construct(ReportSchedulingService $reportSchedulingService)
    {
        parent::__construct();
        $this->reportSchedulingService = $reportSchedulingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->argument('type');

        $this->info("Starting {$type} report scheduling...");

        try {
            switch ($type) {
                case 'daily':
                    $this->reportSchedulingService->scheduleDailyReports();
                    $this->info('Daily reports scheduled successfully.');
                    break;

                case 'weekly':
                    $this->reportSchedulingService->scheduleWeeklyReports();
                    $this->info('Weekly reports scheduled successfully.');
                    break;

                case 'monthly':
                    $this->reportSchedulingService->scheduleMonthlyReports();
                    $this->info('Monthly reports scheduled successfully.');
                    break;

                default:
                    $this->error("Invalid report type: {$type}");
                    $this->info('Available types: daily, weekly, monthly');
                    return 1;
            }

            $this->info("{$type} report scheduling completed successfully.");
            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to schedule {$type} reports: " . $e->getMessage());
            Log::error("Report scheduling failed", [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}