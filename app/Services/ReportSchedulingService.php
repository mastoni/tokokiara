<?php

namespace App\Services;

use App\Models\User;
use App\Models\Store;
use App\Services\ReportGenerationService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReportSchedulingService
{
    protected $reportService;

    public function __construct(ReportGenerationService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Schedule daily summary reports
     */
    public function scheduleDailyReports()
    {
        Log::info('Starting daily report scheduling');

        $stores = Store::all();

        foreach ($stores as $store) {
            // Get store managers and admins who should receive daily reports
            $recipients = User::where('store_id', $store->id)
                ->where('is_active', true)
                ->whereIn('user_role', ['admin', 'manager'])
                ->get()
                ->pluck('email')
                ->toArray();

            if (!empty($recipients)) {
                try {
                    $summary = $this->reportService->generateDailySummary($store->id);

                    // Queue email with report attachment
                    $this->sendReportEmail(
                        $recipients,
                        'Daily Sales Summary - ' . $store->name,
                        'emails.reports.daily-summary',
                        ['summary' => $summary, 'store' => $store]
                    );

                    Log::info("Daily report scheduled for store: {$store->name}");
                } catch (\Exception $e) {
                    Log::error("Failed to schedule daily report for store {$store->id}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Schedule weekly reports
     */
    public function scheduleWeeklyReports()
    {
        Log::info('Starting weekly report scheduling');

        $stores = Store::all();

        foreach ($stores as $store) {
            $recipients = User::where('store_id', $store->id)
                ->where('is_active', true)
                ->whereIn('user_role', ['admin', 'manager'])
                ->get()
                ->pluck('email')
                ->toArray();

            if (!empty($recipients)) {
                try {
                    $filters = [
                        'period' => '7days',
                        'store_id' => $store->id
                    ];

                    $salesReport = $this->reportService->generateSalesReport($filters);
                    $inventoryReport = $this->reportService->generateInventoryReport(['store_id' => $store->id]);

                    $this->sendReportEmail(
                        $recipients,
                        'Weekly Performance Report - ' . $store->name,
                        'emails.reports.weekly-summary',
                        [
                            'salesReport' => $salesReport,
                            'inventoryReport' => $inventoryReport,
                            'store' => $store
                        ]
                    );

                    Log::info("Weekly report scheduled for store: {$store->name}");
                } catch (\Exception $e) {
                    Log::error("Failed to schedule weekly report for store {$store->id}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Schedule monthly reports
     */
    public function scheduleMonthlyReports()
    {
        Log::info('Starting monthly report scheduling');

        $stores = Store::all();

        foreach ($stores as $store) {
            $recipients = User::where('store_id', $store->id)
                ->where('is_active', true)
                ->whereIn('user_role', ['admin', 'manager'])
                ->get()
                ->pluck('email')
                ->toArray();

            if (!empty($recipients)) {
                try {
                    $filters = [
                        'period' => 'last_month',
                        'store_id' => $store->id
                    ];

                    $salesReport = $this->reportService->generateSalesReport($filters);
                    $financialReport = $this->reportService->generateFinancialReport($filters);
                    $customerReport = $this->reportService->generateCustomerReport($filters);

                    $this->sendReportEmail(
                        $recipients,
                        'Monthly Business Report - ' . $store->name,
                        'emails.reports.monthly-summary',
                        [
                            'salesReport' => $salesReport,
                            'financialReport' => $financialReport,
                            'customerReport' => $customerReport,
                            'store' => $store
                        ]
                    );

                    Log::info("Monthly report scheduled for store: {$store->name}");
                } catch (\Exception $e) {
                    Log::error("Failed to schedule monthly report for store {$store->id}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Schedule custom report
     */
    public function scheduleCustomReport($reportType, $recipients, $filters = [], $schedule = null)
    {
        try {
            $reportData = null;

            switch ($reportType) {
                case 'sales':
                    $reportData = $this->reportService->generateSalesReport($filters);
                    break;
                case 'inventory':
                    $reportData = $this->reportService->generateInventoryReport($filters);
                    break;
                case 'financial':
                    $reportData = $this->reportService->generateFinancialReport($filters);
                    break;
                case 'customers':
                    $reportData = $this->reportService->generateCustomerReport($filters);
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported report type: {$reportType}");
            }

            // Generate PDF attachment
            $pdfPath = $this->generatePdfReport($reportType, $reportData);

            // Send email with report
            $this->sendReportEmail(
                $recipients,
                ucfirst($reportType) . ' Report',
                'emails.reports.custom-report',
                ['reportType' => $reportType, 'reportData' => $reportData],
                $pdfPath
            );

            Log::info("Custom {$reportType} report scheduled and sent to: " . implode(', ', $recipients));

            return [
                'success' => true,
                'message' => 'Report scheduled successfully',
                'report_type' => $reportType,
                'recipients' => $recipients
            ];

        } catch (\Exception $e) {
            Log::error("Failed to schedule custom {$reportType} report: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to schedule report: ' . $e->getMessage(),
                'report_type' => $reportType
            ];
        }
    }

    /**
     * Send report email
     */
    private function sendReportEmail($recipients, $subject, $template, $data, $attachmentPath = null)
    {
        foreach ($recipients as $recipient) {
            try {
                $mail = Mail::to($recipient);

                if ($attachmentPath && file_exists($attachmentPath)) {
                    $mail->attach($attachmentPath);
                }

                $mail->send(new \App\Mail\ReportEmail($subject, $template, $data));

                Log::info("Report email sent to: {$recipient}");
            } catch (\Exception $e) {
                Log::error("Failed to send report email to {$recipient}: " . $e->getMessage());
            }
        }
    }

    /**
     * Generate PDF report
     */
    private function generatePdfReport($reportType, $reportData)
    {
        $filename = $reportType . '_report_' . time() . '.pdf';
        $path = storage_path('app/reports/' . $filename);

        // Ensure reports directory exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', $reportData)
            ->setPaper('a4')
            ->setOrientation('landscape');

        $pdf->save($path);

        return $path;
    }

    /**
     * Get scheduled reports configuration
     */
    public function getScheduledReportsConfig()
    {
        return [
            'daily' => [
                'enabled' => true,
                'time' => '08:00',
                'recipients' => ['store_managers', 'admins'],
                'reports' => ['daily_summary']
            ],
            'weekly' => [
                'enabled' => true,
                'day' => 'monday',
                'time' => '09:00',
                'recipients' => ['store_managers', 'admins'],
                'reports' => ['sales', 'inventory']
            ],
            'monthly' => [
                'enabled' => true,
                'day' => 1, // 1st of the month
                'time' => '09:00',
                'recipients' => ['store_managers', 'admins'],
                'reports' => ['sales', 'financial', 'customers']
            ]
        ];
    }

    /**
     * Update scheduled reports configuration
     */
    public function updateScheduledReportsConfig($config)
    {
        // Store configuration in database or cache
        // This would typically be stored in a settings table
        Log::info('Report scheduling configuration updated', $config);

        return true;
    }

    /**
     * Get report delivery logs
     */
    public function getReportDeliveryLogs($filters = [])
    {
        // This would typically query a report_logs table
        return [
            'total_sent' => 0,
            'total_failed' => 0,
            'recent_deliveries' => [],
            'failed_deliveries' => []
        ];
    }
}