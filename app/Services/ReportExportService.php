<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PaymentsExport;
use App\Exports\RevenueExport;
use App\Exports\StudentPatternsExport;
use App\Exports\AgingReportExport;
use App\Exports\CourseRevenueExport;

class ReportExportService
{
    /**
     * Export revenue report
     */
    public function exportRevenueReport(array $data, string $format): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        
        switch ($format) {
            case 'pdf':
                return $this->generateRevenuePDF($data, $user);
            case 'xlsx':
                return $this->generateRevenueExcel($data, $user);
            case 'csv':
                return $this->generateRevenueCSV($data, $user);
            default:
                throw new \Exception('Unsupported export format');
        }
    }

    /**
     * Export payment methods report
     */
    public function exportPaymentMethodsReport(array $data, string $format): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        
        switch ($format) {
            case 'pdf':
                return $this->generatePaymentMethodsPDF($data, $user);
            case 'xlsx':
                return $this->generatePaymentMethodsExcel($data, $user);
            case 'csv':
                return $this->generatePaymentMethodsCSV($data, $user);
            default:
                throw new \Exception('Unsupported export format');
        }
    }

    /**
     * Export student patterns report
     */
    public function exportStudentPatternsReport(array $data, string $format): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        
        switch ($format) {
            case 'pdf':
                return $this->generateStudentPatternsPDF($data, $user);
            case 'xlsx':
                return Excel::download(new StudentPatternsExport($data), 'student-patterns.xlsx');
            case 'csv':
                return $this->generateStudentPatternsCSV($data, $user);
            default:
                throw new \Exception('Unsupported export format');
        }
    }

    /**
     * Export aging report
     */
    public function exportAgingReport(array $data, string $format): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        
        switch ($format) {
            case 'pdf':
                return $this->generateAgingReportPDF($data, $user);
            case 'xlsx':
                return Excel::download(new AgingReportExport($data), 'aging-report.xlsx');
            case 'csv':
                return $this->generateAgingReportCSV($data, $user);
            default:
                throw new \Exception('Unsupported export format');
        }
    }

    /**
     * Export course revenue report
     */
    public function exportCourseRevenueReport(array $data, string $format): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        
        switch ($format) {
            case 'pdf':
                return $this->generateCourseRevenuePDF($data, $user);
            case 'xlsx':
                return Excel::download(new CourseRevenueExport($data), 'course-revenue.xlsx');
            case 'csv':
                return $this->generateCourseRevenueCSV($data, $user);
            default:
                throw new \Exception('Unsupported export format');
        }
    }

    // PDF Generation Methods
    private function generateRevenuePDF(array $data, $user): \Illuminate\Http\Response
    {
        $pdf = \PDF::loadView('pdf.revenue-report', [
            'data' => $data,
            'user' => $user,
            'generated_at' => now(),
        ]);

        return $pdf->download('revenue-report.pdf');
    }

    private function generatePaymentMethodsPDF(array $data, $user): \Illuminate\Http\Response
    {
        $pdf = \PDF::loadView('pdf.payment-methods-report', [
            'data' => $data,
            'user' => $user,
            'generated_at' => now(),
        ]);

        return $pdf->download('payment-methods-report.pdf');
    }

    private function generateStudentPatternsPDF(array $data, $user): \Illuminate\Http\Response
    {
        $pdf = \PDF::loadView('pdf.student-patterns-report', [
            'data' => $data,
            'user' => $user,
            'generated_at' => now(),
        ]);

        return $pdf->download('student-patterns-report.pdf');
    }

    private function generateAgingReportPDF(array $data, $user): \Illuminate\Http\Response
    {
        $pdf = \PDF::loadView('pdf.aging-report', [
            'data' => $data,
            'user' => $user,
            'generated_at' => now(),
        ]);

        return $pdf->download('aging-report.pdf');
    }

    private function generateCourseRevenuePDF(array $data, $user): \Illuminate\Http\Response
    {
        $pdf = \PDF::loadView('pdf.course-revenue-report', [
            'data' => $data,
            'user' => $user,
            'generated_at' => now(),
        ]);

        return $pdf->download('course-revenue-report.pdf');
    }

    // Excel Generation Methods
    private function generateRevenueExcel(array $data, $user): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(new RevenueExport($data), 'revenue-report.xlsx');
    }

    private function generatePaymentMethodsExcel(array $data, $user): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return Excel::download(new PaymentsExport($data), 'payment-methods.xlsx');
    }

    // CSV Generation Methods
    private function generateRevenueCSV(array $data, $user): \Illuminate\Http\Response
    {
        $filename = 'revenue-report-' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w');

        // CSV Header
        fputcsv($handle, ['Period', 'Total Amount', 'Transaction Count']);

        // CSV Data
        foreach ($data['data'] as $row) {
            fputcsv($handle, [
                $row['period'],
                $row['total_amount'],
                $row['transaction_count'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function generatePaymentMethodsCSV(array $data, $user): \Illuminate\Http\Response
    {
        $filename = 'payment-methods-' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w');

        // CSV Header
        fputcsv($handle, ['Payment Method', 'Amount', 'Count', 'Percentage']);

        // CSV Data
        foreach ($data['methods'] as $method) {
            fputcsv($handle, [
                $method['method'],
                $method['amount'],
                $method['count'],
                $method['percentage'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function generateStudentPatternsCSV(array $data, $user): \Illuminate\Http\Response
    {
        $filename = 'student-patterns-' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w');

        // CSV Header
        fputcsv($handle, ['Student ID', 'Student Name', 'Total Payments', 'Average Amount', 'Payment Frequency']);

        // CSV Data - Based on analysis type
        if (isset($data['timeliness'])) {
            fputcsv($handle, ['Timeliness Analysis Data would go here']);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function generateAgingReportCSV(array $data, $user): \Illuminate\Http\Response
    {
        $filename = 'aging-report-' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w');

        // CSV Header
        fputcsv($handle, ['Student ID', 'Student Name', 'Outstanding Balance', 'Days Outstanding', 'Age Bucket']);

        // CSV Data
        foreach ($data['students'] as $student) {
            fputcsv($handle, [
                $student['student_id_number'],
                $student['student_name'],
                $student['outstanding_balance'],
                $student['days_outstanding'],
                $student['age_bucket'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function generateCourseRevenueCSV(array $data, $user): \Illuminate\Http\Response
    {
        $filename = 'course-revenue-' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'w');

        // CSV Header
        fputcsv($handle, ['Group Key', 'Total Amount', 'Transaction Count']);

        // CSV Data
        foreach ($data['data'] as $row) {
            fputcsv($handle, [
                $row['group_key'],
                $row['total_amount'],
                $row['transaction_count'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}