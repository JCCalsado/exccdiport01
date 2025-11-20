<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Services\ReportExportService;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Student;
use App\Models\Fee;
use App\Models\User;

class ReportController extends Controller
{
    public function __construct(
        private ReportExportService $reportExportService
    ) {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (!$user || (!$user->isAdmin() && !$user->isAccounting())) {
                abort(403, 'Unauthorized access');
            }
            return $next($request);
        });
    }

    /**
     * Display reports dashboard
     */
    public function index()
    {
        return Inertia::render('Reports/Index', [
            'availableReports' => $this->getAvailableReports(),
            'recentExports' => $this->getRecentExports(),
            'systemStats' => $this->getSystemStats(),
        ]);
    }

    /**
     * Generate revenue reports
     */
    public function revenue(Request $request)
    {
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:web,pdf,xlsx,csv',
            'gateway' => 'nullable|in:gcash,paypal,stripe,cash,all',
        ]);

        try {
            $period = $request->input('period');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $gateway = $request->input('gateway', 'all');
            $format = $request->input('format', 'web');

            $data = $this->generateRevenueData($period, $startDate, $endDate, $gateway);

            if ($format === 'web') {
                return Inertia::render('Reports/Revenue', [
                    'data' => $data,
                    'filters' => $request->only(['period', 'start_date', 'end_date', 'gateway']),
                    'chartData' => $this->prepareRevenueChartData($data),
                ]);
            }

            return $this->reportExportService->exportRevenueReport($data, $format);

        } catch (\Exception $e) {
            Log::error('Revenue report generation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to generate revenue report: ' . $e->getMessage());
        }
    }

    /**
     * Generate payment method breakdown report
     */
    public function paymentMethods(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:web,pdf,xlsx,csv',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $format = $request->input('format', 'web');

            $data = $this->generatePaymentMethodsData($startDate, $endDate);

            if ($format === 'web') {
                return Inertia::render('Reports/PaymentMethods', [
                    'data' => $data,
                    'filters' => $request->only(['start_date', 'end_date']),
                    'chartData' => $this->preparePaymentMethodsChartData($data),
                ]);
            }

            return $this->reportExportService->exportPaymentMethodsReport($data, $format);

        } catch (\Exception $e) {
            Log::error('Payment methods report generation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to generate payment methods report: ' . $e->getMessage());
        }
    }

    /**
     * Generate student payment patterns report
     */
    public function studentPatterns(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:web,pdf,xlsx,csv',
            'analysis_type' => 'nullable|in:timeliness,frequency,amount,delinquency',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $format = $request->input('format', 'web');
            $analysisType = $request->input('analysis_type', 'all');

            $data = $this->generateStudentPatternsData($startDate, $endDate, $analysisType);

            if ($format === 'web') {
                return Inertia::render('Reports/StudentPatterns', [
                    'data' => $data,
                    'filters' => $request->only(['start_date', 'end_date', 'analysis_type']),
                    'chartData' => $this->prepareStudentPatternsChartData($data, $analysisType),
                ]);
            }

            return $this->reportExportService->exportStudentPatternsReport($data, $format);

        } catch (\Exception $e) {
            Log::error('Student patterns report generation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to generate student patterns report: ' . $e->getMessage());
        }
    }

    /**
     * Generate aging report
     */
    public function agingReport(Request $request)
    {
        $request->validate([
            'as_of_date' => 'required|date',
            'aging_buckets' => 'nullable|array',
            'aging_buckets.*' => 'integer|min:0',
            'include_graduated' => 'boolean',
            'format' => 'nullable|in:web,pdf,xlsx,csv',
        ]);

        try {
            $asOfDate = $request->input('as_of_date');
            $agingBuckets = $request->input('aging_buckets', [30, 60, 90, 180, 365]);
            $includeGraduated = $request->input('include_graduated', false);
            $format = $request->input('format', 'web');

            $data = $this->generateAgingReportData($asOfDate, $agingBuckets, $includeGraduated);

            if ($format === 'web') {
                return Inertia::render('Reports/Aging', [
                    'data' => $data,
                    'filters' => $request->only(['as_of_date', 'aging_buckets', 'include_graduated']),
                    'chartData' => $this->prepareAgingChartData($data),
                ]);
            }

            return $this->reportExportService->exportAgingReport($data, $format);

        } catch (\Exception $e) {
            Log::error('Aging report generation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to generate aging report: ' . $e->getMessage());
        }
    }

    /**
     * Generate course revenue analysis report
     */
    public function courseRevenue(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'nullable|in:web,pdf,xlsx,csv',
            'group_by' => 'nullable|in:course,year_level,department',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $format = $request->input('format', 'web');
            $groupBy = $request->input('group_by', 'course');

            $data = $this->generateCourseRevenueData($startDate, $endDate, $groupBy);

            if ($format === 'web') {
                return Inertia::render('Reports/CourseRevenue', [
                    'data' => $data,
                    'filters' => $request->only(['start_date', 'end_date', 'group_by']),
                    'chartData' => $this->prepareCourseRevenueChartData($data),
                ]);
            }

            return $this->reportExportService->exportCourseRevenueReport($data, $format);

        } catch (\Exception $e) {
            Log::error('Course revenue report generation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return back()->with('error', 'Failed to generate course revenue report: ' . $e->getMessage());
        }
    }

    /**
     * Get dashboard data for reports
     */
    public function dashboardData()
    {
        try {
            $data = [
                'total_payments' => Payment::where('status', Payment::STATUS_COMPLETED)->count(),
                'total_revenue' => Payment::where('status', Payment::STATUS_COMPLETED)->sum('amount'),
                'pending_payments' => Payment::where('status', Payment::STATUS_PENDING)->count(),
                'failed_payments' => Payment::where('status', Payment::STATUS_FAILED)->count(),
                'recent_payments' => Payment::with(['student.user'])
                    ->where('created_at', '>=', now()->subDays(7))
                    ->orderBy('created_at', 'desc')
                    ->take(10)
                    ->get(),
                'payment_methods_breakdown' => $this->getPaymentMethodsBreakdown(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get reports dashboard data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
            ], 500);
        }
    }

    /**
     * Generate revenue data
     */
    private function generateRevenueData(string $period, string $startDate, string $endDate, string $gateway): array
    {
        $query = Payment::where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($gateway !== 'all') {
            $query->whereHas('latestGatewayDetail', function ($q) use ($gateway) {
                $q->where('gateway', $gateway);
            });
        }

        switch ($period) {
            case 'daily':
                $query->selectRaw('DATE(created_at) as period, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                    ->groupBy('period')
                    ->orderBy('period');
                break;
            case 'weekly':
                $query->selectRaw('YEAR(created_at) as year, WEEK(created_at) as week, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                    ->groupBy('year', 'week')
                    ->orderBy('year', 'week');
                break;
            case 'monthly':
                $query->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as period, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                    ->groupBy('period')
                    ->orderBy('period');
                break;
            case 'yearly':
                $query->selectRaw('YEAR(created_at) as period, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                    ->groupBy('period')
                    ->orderBy('period');
                break;
        }

        $results = $query->get();
        
        return [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'gateway' => $gateway,
            'data' => $results,
            'summary' => [
                'total_amount' => $results->sum('total_amount'),
                'total_transactions' => $results->count(),
                'average_amount' => $results->avg('total_amount') ?? 0,
            ],
        ];
    }

    /**
     * Generate payment methods data
     */
    private function generatePaymentMethodsData(string $startDate, string $endDate): array
    {
        $payments = Payment::where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('latestGatewayDetail')
            ->get();

        $methods = $payments->groupBy(function ($payment) {
            return $payment->latestGatewayDetail->gateway ?? 'unknown';
        })->map(function ($payments, $method) {
            $totalAmount = $payments->sum('amount');
            
            return [
                'method' => $method,
                'amount' => $totalAmount,
                'count' => $payments->count(),
                'percentage' => $totalAmount > 0 ? ($totalAmount / $payments->sum('amount')) * 100 : 0,
            ];
        })->values();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'methods' => $methods,
            'summary' => [
                'total_amount' => $payments->sum('amount'),
                'total_transactions' => $payments->count(),
            ],
        ];
    }

    /**
     * Generate student patterns data
     */
    private function generateStudentPatternsData(string $startDate, string $endDate, string $analysisType): array
    {
        $data = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'analysis_type' => $analysisType,
        ];

        switch ($analysisType) {
            case 'timeliness':
                $data['timeliness'] = $this->analyzePaymentTimeliness($startDate, $endDate);
                break;
            case 'frequency':
                $data['frequency'] = $this->analyzePaymentFrequency($startDate, $endDate);
                break;
            case 'amount':
                $data['amount_patterns'] = $this->analyzePaymentAmounts($startDate, $endDate);
                break;
            case 'delinquency':
                $data['delinquency'] = $this->analyzeDelinquency($startDate, $endDate);
                break;
            default:
                $data['timeliness'] = $this->analyzePaymentTimeliness($startDate, $endDate);
                $data['frequency'] = $this->analyzePaymentFrequency($startDate, $endDate);
                $data['amount_patterns'] = $this->analyzePaymentAmounts($startDate, $endDate);
                $data['delinquency'] = $this->analyzeDelinquency($startDate, $endDate);
                break;
        }

        return $data;
    }

    /**
     * Generate aging report data
     */
    private function generateAgingReportData(string $asOfDate, array $agingBuckets, bool $includeGraduated): array
    {
        $studentsQuery = Student::with(['user', 'feeItems']);

        if (!$includeGraduated) {
            $studentsQuery->where('status', 'active');
        }

        $students = $studentsQuery->get();

        $agingData = [];
        $totalOutstanding = 0;

        foreach ($students as $student) {
            $outstandingBalance = $student->feeItems->sum('balance');

            if ($outstandingBalance > 0) {
                $lastPayment = $student->payments()
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $daysOutstanding = $lastPayment 
                    ? max(0, now()->diffInDays($lastPayment->created_at))
                    : 999;

                $ageBucket = $this->determineAgeBucket($daysOutstanding, $agingBuckets);

                $agingData[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->user->name,
                    'student_id_number' => $student->student_id,
                    'outstanding_balance' => $outstandingBalance,
                    'days_outstanding' => $daysOutstanding,
                    'age_bucket' => $ageBucket,
                    'last_payment_date' => $lastPayment?->created_at,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                ];

                $totalOutstanding += $outstandingBalance;
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'aging_buckets' => $agingBuckets,
            'include_graduated' => $includeGraduated,
            'students' => $agingData,
            'summary' => [
                'total_students' => count($agingData),
                'total_outstanding' => $totalOutstanding,
                'average_balance' => count($agingData) > 0 ? $totalOutstanding / count($agingData) : 0,
            ],
            'bucket_summary' => $this->generateBucketSummary($agingData, $agingBuckets),
        ];
    }

    /**
     * Generate course revenue data
     */
    private function generateCourseRevenueData(string $startDate, string $endDate, string $groupBy): array
    {
        $query = Payment::where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with('student');

        switch ($groupBy) {
            case 'course':
                $query->selectRaw('student.course as group_key, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                        ->groupBy('student.course');
                break;
            case 'year_level':
                $query->selectRaw('student.year_level as group_key, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                        ->groupBy('student.year_level');
                break;
            case 'department':
                $query->selectRaw('SUBSTRING(student.course, 1, 3) as group_key, SUM(amount) as total_amount, COUNT(*) as transaction_count')
                        ->groupBy('group_key');
                break;
        }

        $results = $query->get();

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'group_by' => $groupBy,
            'data' => $results,
            'summary' => [
                'total_amount' => $results->sum('total_amount'),
                'total_transactions' => $results->count(),
                'unique_courses' => $results->unique('group_key')->count('group_key') ?? 0,
            ],
        ];
    }

    /**
     * Helper: Get available reports
     */
    private function getAvailableReports(): array
    {
        return [
            [
                'id' => 'revenue',
                'name' => 'Revenue Report',
                'description' => 'Analyze payment revenue over time periods',
                'icon' => 'CurrencyDollar',
                'filters' => ['period', 'gateway', 'date_range'],
            ],
            [
                'id' => 'payment_methods',
                'name' => 'Payment Methods Analysis',
                'description' => 'Breakdown of payment methods usage',
                'icon' => 'CreditCard',
                'filters' => ['date_range'],
            ],
            [
                'id' => 'student_patterns',
                'name' => 'Student Payment Patterns',
                'description' => 'Analyze student payment behavior patterns',
                'icon' => 'ChartBar',
                'filters' => ['date_range', 'analysis_type'],
            ],
            [
                'id' => 'aging',
                'name' => 'Aging Report',
                'description' => 'Analyze overdue payments by age buckets',
                'icon' => 'Clock',
                'filters' => ['as_of_date', 'aging_buckets', 'include_graduated'],
            ],
            [
                'id' => 'course_revenue',
                'name' => 'Course Revenue Analysis',
                'description' => 'Revenue analysis by course and year level',
                'icon' => 'AcademicCap',
                'filters' => ['date_range', 'group_by'],
            ],
        ];
    }

    /**
     * Helper: Get recent exports
     */
    private function getRecentExports(): array
    {
        // This would typically query a database table for export history
        // For now, return empty array
        return [];
    }

    /**
     * Helper: Get system stats
     */
    private function getSystemStats(): array
    {
        return [
            'total_students' => Student::count(),
            'active_students' => Student::where('status', 'active')->count(),
            'total_revenue' => Payment::where('status', Payment::STATUS_COMPLETED)->sum('amount'),
            'pending_payments' => Payment::where('status', Payment::STATUS_PENDING)->count(),
        ];
    }

    /**
     * Helper: Get payment methods breakdown
     */
    private function getPaymentMethodsBreakdown(): array
    {
        return Payment::where('status', Payment::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subDays(30))
            ->join('payment_gateway_details', 'payments.id', '=', 'payment_gateway_details.payment_id')
            ->selectRaw('payment_gateway_details.gateway, COUNT(*) as count, SUM(payments.amount) as total')
            ->groupBy('payment_gateway_details.gateway')
            ->get()
            ->toArray();
    }

    /**
     * Helper: Analyze payment timeliness
     */
    private function analyzePaymentTimeliness(string $startDate, string $endDate): array
    {
        // Implementation for payment timeliness analysis
        return [];
    }

    /**
     * Helper: Analyze payment frequency
     */
    private function analyzePaymentFrequency(string $startDate, string $endDate): array
    {
        // Implementation for payment frequency analysis
        return [];
    }

    /**
     * Helper: Analyze payment amounts
     */
    private function analyzePaymentAmounts(string $startDate, string $endDate): array
    {
        // Implementation for payment amount analysis
        return [];
    }

    /**
     * Helper: Analyze delinquency
     */
    private function analyzeDelinquency(string $startDate, string $endDate): array
    {
        // Implementation for delinquency analysis
        return [];
    }

    /**
     * Helper: Determine age bucket
     */
    private function determineAgeBucket(int $daysOutstanding, array $buckets): string
    {
        sort($buckets);
        
        foreach ($buckets as $bucket) {
            if ($daysOutstanding <= $bucket) {
                return "1-{$bucket} days";
            }
        }
        
        return ($buckets[count($buckets) - 1]) . "+ days";
    }

    /**
     * Helper: Generate bucket summary
     */
    private function generateBucketSummary(array $agingData, array $buckets): array
    {
        $summary = [];
        
        foreach ($buckets as $bucket) {
            $bucketKey = "1-{$bucket}_days";
            $summary[$bucketKey] = [
                'count' => 0,
                'amount' => 0,
            ];
        }
        
        foreach ($agingData as $data) {
            $bucketKey = $data['age_bucket'];
            if (isset($summary[$bucketKey])) {
                $summary[$bucketKey]['count']++;
                $summary[$bucketKey]['amount'] += $data['outstanding_balance'];
            }
        }
        
        return $summary;
    }

    /**
     * Helper: Prepare revenue chart data
     */
    private function prepareRevenueChartData(array $data): array
    {
        return [
            'labels' => $data['data']->pluck('period'),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data['data']->pluck('total_amount'),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                ],
                [
                    'label' => 'Transactions',
                    'data' => $data['data']->pluck('transaction_count'),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    /**
     * Helper: Prepare payment methods chart data
     */
    private function preparePaymentMethodsChartData(array $data): array
    {
        return [
            'labels' => $data['methods']->pluck('method'),
            'datasets' => [
                [
                    'label' => 'Amount',
                    'data' => $data['methods']->pluck('amount'),
                    'backgroundColor' => [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)',
                    ],
                ],
            ],
        ];
    }

    /**
     * Helper: Prepare student patterns chart data
     */
    private function prepareStudentPatternsChartData(array $data, string $analysisType): array
    {
        // Implementation based on analysis type
        return [];
    }

    /**
     * Helper: Prepare aging chart data
     */
    private function prepareAgingChartData(array $data): array
    {
        $bucketLabels = array_keys($data['bucket_summary']);
        $bucketCounts = array_map(fn($bucket) => $bucket['count'], $data['bucket_summary']);
        
        return [
            'labels' => $bucketLabels,
            'datasets' => [
                [
                    'label' => 'Number of Students',
                    'data' => $bucketCounts,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                ],
            ],
        ];
    }

    /**
     * Helper: Prepare course revenue chart data
     */
    private function prepareCourseRevenueChartData(array $data): array
    {
        return [
            'labels' => $data['data']->pluck('group_key'),
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data['data']->pluck('total_amount'),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                ],
            ],
        ];
    }
}