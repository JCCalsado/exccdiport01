<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportExportService
{
    /**
     * Export revenue report to specified format
     */
    public function exportRevenueReport(array $data, string $format)
    {
        switch ($format) {
            case 'pdf':
                return $this->exportRevenuePDF($data);
            case 'xlsx':
                return $this->exportRevenueExcel($data);
            case 'csv':
                return $this->exportRevenueCSV($data);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    /**
     * Export payment methods report
     */
    public function exportPaymentMethodsReport(array $data, string $format)
    {
        switch ($format) {
            case 'pdf':
                return $this->exportPaymentMethodsPDF($data);
            case 'xlsx':
                return $this->exportPaymentMethodsExcel($data);
            case 'csv':
                return $this->exportPaymentMethodsCSV($data);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    /**
     * Export student patterns report
     */
    public function exportStudentPatternsReport(array $data, string $format)
    {
        switch ($format) {
            case 'pdf':
                return $this->exportStudentPatternsPDF($data);
            case 'xlsx':
                return $this->exportStudentPatternsExcel($data);
            case 'csv':
                return $this->exportStudentPatternsCSV($data);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    /**
     * Export aging report
     */
    public function exportAgingReport(array $data, string $format)
    {
        switch ($format) {
            case 'pdf':
                return $this->exportAgingPDF($data);
            case 'xlsx':
                return $this->exportAgingExcel($data);
            case 'csv':
                return $this->exportAgingCSV($data);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    /**
     * Export course revenue report
     */
    public function exportCourseRevenueReport(array $data, string $format)
    {
        switch ($format) {
            case 'pdf':
                return $this->exportCourseRevenuePDF($data);
            case 'xlsx':
                return $this->exportCourseRevenueExcel($data);
            case 'csv':
                return $this->exportCourseRevenueCSV($data);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }

    /**
     * Export revenue report as PDF
     */
    private function exportRevenuePDF(array $data)
    {
        $pdf = Pdf::loadView('pdfs.reports.revenue', [
            'data' => $data,
            'generated_at' => now()->format('M d, Y h:i A'),
            'generated_by' => auth()->user()->name,
            'title' => 'Revenue Report',
            'period' => "{$data['period']} from {$data['start_date']} to {$data['end_date']}",
        ]);

        $filename = "revenue_report_{$data['period']}_{$data['start_date']}_{$data['end_date']}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Export revenue report as Excel
     */
    private function exportRevenueExcel(array $data)
    {
        $exportData = $this->prepareRevenueExcelData($data);
        $filename = "revenue_report_{$data['period']}_{$data['start_date']}_{$data['end_date']}.xlsx";

        return Excel::download(new class($exportData) {
            private $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return ['Period', 'Gateway', 'Transaction Count', 'Total Amount', 'Average Amount'];
            }
        }, $filename);
    }

    /**
     * Export revenue report as CSV
     */
    private function exportRevenueCSV(array $data)
    {
        $exportData = $this->prepareRevenueExcelData($data);
        $filename = "revenue_report_{$data['period']}_{$data['start_date']}_{$data['end_date']}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');

            // Add CSV header
            fputcsv($file, ['Period', 'Gateway', 'Transaction Count', 'Total Amount', 'Average Amount']);

            // Add data rows
            foreach ($exportData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Prepare revenue data for Excel/CSV export
     */
    private function prepareRevenueExcelData(array $data): array
    {
        $rows = [];

        foreach ($data['data'] as $gateway => $gatewayData) {
            foreach ($gatewayData as $periodData) {
                $rows[] = [
                    $periodData['period'],
                    ucfirst($gateway),
                    $periodData['transaction_count'],
                    $periodData['total_amount'],
                    $periodData['average_amount'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Export payment methods report as PDF
     */
    private function exportPaymentMethodsPDF(array $data)
    {
        $pdf = Pdf::loadView('pdfs.reports.payment-methods', [
            'data' => $data,
            'generated_at' => now()->format('M d, Y h:i A'),
            'generated_by' => auth()->user()->name,
            'title' => 'Payment Methods Analysis',
            'period' => "From {$data['start_date']} to {$data['end_date']}",
        ]);

        $filename = "payment_methods_report_{$data['start_date']}_{$data['end_date']}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Export payment methods report as Excel
     */
    private function exportPaymentMethodsExcel(array $data)
    {
        $exportData = $this->preparePaymentMethodsExcelData($data);
        $filename = "payment_methods_report_{$data['start_date']}_{$data['end_date']}.xlsx";

        return Excel::download(new class($exportData) {
            private $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Payment Method',
                    'Transaction Count',
                    'Total Amount',
                    'Average Amount',
                    'Minimum Amount',
                    'Maximum Amount',
                    'Success Rate (%)'
                ];
            }
        }, $filename);
    }

    /**
     * Prepare payment methods data for Excel/CSV export
     */
    private function preparePaymentMethodsExcelData(array $data): array
    {
        $rows = [];

        foreach ($data['method_stats'] as $method => $stats) {
            $rows[] = [
                ucfirst(str_replace('_', ' ', $method)),
                $stats['transaction_count'],
                $stats['total_amount'],
                $stats['average_amount'],
                $stats['min_amount'],
                $stats['max_amount'],
                round($stats['success_rate'], 2),
            ];
        }

        return $rows;
    }

    /**
     * Export payment methods report as CSV
     */
    private function exportPaymentMethodsCSV(array $data)
    {
        $exportData = $this->preparePaymentMethodsExcelData($data);
        $filename = "payment_methods_report_{$data['start_date']}_{$data['end_date']}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Payment Method',
                'Transaction Count',
                'Total Amount',
                'Average Amount',
                'Minimum Amount',
                'Maximum Amount',
                'Success Rate (%)'
            ]);

            foreach ($exportData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export student patterns report as PDF
     */
    private function exportStudentPatternsPDF(array $data)
    {
        $pdf = Pdf::loadView('pdfs.reports.student-patterns', [
            'data' => $data,
            'generated_at' => now()->format('M d, Y h:i A'),
'generated_by' => auth()->user()->name,
            'title' => 'Student Payment Patterns',
            'period' => "From {$data['start_date']} to {$data['end_date']}",
        ]);

        $filename = "student_patterns_report_{$data['start_date']}_{$data['end_date']}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Export student patterns report as Excel
     */
    private function exportStudentPatternsExcel(array $data)
    {
        $exportData = $this->prepareStudentPatternsExcelData($data);
        $filename = "student_patterns_report_{$data['start_date']}_{$data['end_date']}.xlsx";

        return Excel::download(new class($exportData) {
            private $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Student Name',
                    'Student ID',
                    'Course',
                    'Year Level',
                    'Payment Count',
                    'Total Paid',
                    'Average Payment Amount',
                    'First Payment Date',
                    'Last Payment Date',
                    'Payment Methods Used'
                ];
            }
        }, $filename);
    }

    /**
     * Prepare student patterns data for Excel/CSV export
     */
    private function prepareStudentPatternsExcelData(array $data): array
    {
        $rows = [];

        foreach ($data['patterns'] as $pattern) {
            $rows[] = [
                $pattern['student_name'],
                $pattern['student_id_number'],
                $pattern['course'],
                $pattern['year_level'],
                $pattern['payment_count'],
                $pattern['total_paid'],
                $pattern['average_payment_amount'],
                $pattern['first_payment_date'],
                $pattern['last_payment_date'],
                implode(', ', $pattern['payment_methods_used']),
            ];
        }

        return $rows;
    }

    /**
     * Export student patterns report as CSV
     */
    private function exportStudentPatternsCSV(array $data)
    {
        $exportData = $this->prepareStudentPatternsExcelData($data);
        $filename = "student_patterns_report_{$data['start_date']}_{$data['end_date']}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Student Name',
                'Student ID',
                'Course',
                'Year Level',
                'Payment Count',
                'Total Paid',
                'Average Payment Amount',
                'First Payment Date',
                'Last Payment Date',
                'Payment Methods Used'
            ]);

            foreach ($exportData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export aging report as PDF
     */
    private function exportAgingPDF(array $data)
    {
        $pdf = Pdf::loadView('pdfs.reports.aging', [
            'data' => $data,
            'generated_at' => now()->format('M d, Y h:i A'),
            'generated_by' => auth()->user()->name,
            'title' => 'Accounts Receivable Aging Report',
            'as_of_date' => $data['as_of_date'],
        ]);

        $filename = "aging_report_{$data['as_of_date']}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Export aging report as Excel
     */
    private function exportAgingExcel(array $data)
    {
        $exportData = $this->prepareAgingExcelData($data);
        $filename = "aging_report_{$data['as_of_date']}.xlsx";

        return Excel::download(new class($exportData) {
            private $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Aging Bucket',
                    'Student Name',
                    'Student ID',
                    'Course',
                    'Year Level',
                    'Outstanding Balance',
                    'Days Since Last Payment',
                    'Last Payment Date',
                    'Status'
                ];
            }
        }, $filename);
    }

    /**
     * Prepare aging data for Excel/CSV export
     */
    private function prepareAgingExcelData(array $data): array
    {
        $rows = [];

        foreach ($data['aging_data'] as $bucket => $students) {
            foreach ($students as $student) {
                $rows[] = [
                    $bucket,
                    $student['student_name'],
                    $student['student_id_number'],
                    $student['course'],
                    $student['year_level'],
                    $student['outstanding_balance'],
                    $student['days_since_last_payment'],
                    $student['last_payment_date'],
                    $student['status'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Export aging report as CSV
     */
    private function exportAgingCSV(array $data)
    {
        $exportData = $this->prepareAgingExcelData($data);
        $filename = "aging_report_{$data['as_of_date']}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Aging Bucket',
                'Student Name',
                'Student ID',
                'Course',
                'Year Level',
                'Outstanding Balance',
                'Days Since Last Payment',
                'Last Payment Date',
                'Status'
            ]);

            foreach ($exportData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export course revenue report as PDF
     */
    private function exportCourseRevenuePDF(array $data)
    {
        $pdf = Pdf::loadView('pdfs.reports.course-revenue', [
            'data' => $data,
            'generated_at' => now()->format('M d, Y h:i A'),
            'generated_by' => auth()->user()->name,
            'title' => 'Course Revenue Analysis',
            'period' => "{$data['school_year']} - {$data['semester']} Semester",
        ]);

        $filename = "course_revenue_{$data['school_year']}_{$data['semester']}.pdf";

        return $pdf->download($filename);
    }

    /**
     * Export course revenue report as Excel
     */
    private function exportCourseRevenueExcel(array $data)
    {
        $exportData = $this->prepareCourseRevenueExcelData($data);
        $filename = "course_revenue_{$data['school_year']}_{$data['semester']}.xlsx";

        return Excel::download(new class($exportData) {
            private $data;

            public function __construct(array $data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return [
                    'Course',
                    'Student Count',
                    'Total Revenue',
                    'Revenue per Student'
                ];
            }
        }, $filename);
    }

    /**
     * Prepare course revenue data for Excel/CSV export
     */
    private function prepareCourseRevenueExcelData(array $data): array
    {
        $rows = [];

        foreach ($data['course_data'] as $course) {
            $rows[] = [
                $course['course'],
                $course['student_count'],
                $course['total_revenue'],
                $course['revenue_per_student'],
            ];
        }

        return $rows;
    }

    /**
     * Export course revenue report as CSV
     */
    private function exportCourseRevenueCSV(array $data)
    {
        $exportData = $this->prepareCourseRevenueExcelData($data);
        $filename = "course_revenue_{$data['school_year']}_{$data['semester']}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($exportData) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Course',
                'Student Count',
                'Total Revenue',
                'Revenue per Student'
            ]);

            foreach ($exportData as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Generate scheduled reports and send via email
     */
    public function generateScheduledReport(string $reportType, array $parameters, array $recipients)
    {
        try {
            // Generate the report based on type
            $data = $this->generateReportData($reportType, $parameters);

            // Export to PDF
            $filename = $this->generateFilename($reportType, $parameters);
            $pdf = Pdf::loadView("pdfs.reports.{$reportType}", [
                'data' => $data,
                'generated_at' => now()->format('M d, Y h:i A'),
                'generated_by' => 'System',
                'is_scheduled' => true,
                'parameters' => $parameters,
            ]);

            // Save to storage temporarily
            $path = 'reports/scheduled/' . $filename;
            Storage::put($path, $pdf->output());

            // Send email to recipients
            $this->sendScheduledReportEmail($recipients, $filename, $path, $reportType);

            // Clean up after sending
            Storage::delete($path);

            Log::info("Scheduled report generated and sent", [
                'report_type' => $reportType,
                'recipients' => $recipients,
                'filename' => $filename
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to generate scheduled report", [
                'report_type' => $reportType,
                'error' => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return false;
        }
    }

    /**
     * Generate report data based on type
     */
    private function generateReportData(string $reportType, array $parameters): array
    {
        switch ($reportType) {
            case 'revenue':
                return $this->generateRevenueData($parameters);
            case 'payment_methods':
                return $this->generatePaymentMethodsData($parameters);
            case 'aging':
                return $this->generateAgingData($parameters);
            default:
                throw new \Exception("Unknown report type: {$reportType}");
        }
    }

    /**
     * Generate filename for report
     */
    private function generateFilename(string $reportType, array $parameters): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        switch ($reportType) {
            case 'revenue':
                return "revenue_report_{$timestamp}.pdf";
            case 'payment_methods':
                return "payment_methods_report_{$timestamp}.pdf";
            case 'aging':
                return "aging_report_" . ($parameters['as_of_date'] ?? $timestamp) . ".pdf";
            default:
                return "{$reportType}_report_{$timestamp}.pdf";
        }
    }

    /**
     * Send scheduled report via email
     */
    private function sendScheduledReportEmail(array $recipients, string $filename, string $path, string $reportType)
    {
        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient)
                    ->subject("Scheduled {$reportType} Report")
                    ->view('emails.reports.scheduled', [
                        'filename' => $filename,
                        'report_type' => $reportType,
                        'recipient' => $recipient,
                    ])
                    ->attach(Storage::path($path), [
                        'as' => $filename,
                        'mime' => 'application/pdf',
                    ])
                    ->send();

                Log::info("Scheduled report sent to recipient", [
                    'recipient' => $recipient,
                    'filename' => $filename
                ]);

            } catch (\Exception $e) {
                Log::error("Failed to send scheduled report email", [
'recipient' => $recipient,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}