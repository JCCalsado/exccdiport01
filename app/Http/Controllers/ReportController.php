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
        $this->middleware(['auth', 'verified', 'role:admin,accounting']);
    }

    /**
     * Display the reports dashboard
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
            'period' => 'required|in:monthly,quarterly,yearly',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'course' => 'nullable|exists:subjects,name',
            'year_level' => 'nullable|in:1,2,3,4,5',
            'format' => 'nullable|in:web,pdf,xlsx,csv',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $course = $request->input('course');
            $yearLevel = $request->input('year_level');
            $format = $request->input('format', 'web');

            $data = $this->generateStudentPatternsData($startDate, $endDate, $course, $yearLevel);

            if ($format === 'web') {
                return Inertia::render('Reports/StudentPatterns', [
                    'data' => $data,
                    'filters' => $request->only(['period', 'start_date', 'end_date', 'course', 'year_level']),
                    'chartData' => $this->prepareStudentPatternsChartData($data),
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
     * Generate outstanding balances aging report
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
            $agingBuckets = $request->input('aging_buckets', [30, 60, 90, 180]);
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
     * Generate course/program revenue analysis
     */
    public function courseRevenue(Request $request)
    {
        $request->validate([
            'school_year' => 'required|string',
            'semester' => 'required|in:first,second,summer',
            'format' => 'nullable|in:web,pdf,xlsx,csv',
        ]);

        try {
            $schoolYear = $request->input('school_year');
            $semester = $request->input('semester');
            $format = $request->input('format', 'web');

            $data = $this->generateCourseRevenueData($schoolYear, $semester);

            if ($format === 'web') {
                return Inertia::render('Reports/CourseRevenue', [
                    'data' => $data,
                    'filters' => $request->only(['school_year', 'semester']),
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
     * API endpoint for real-time dashboard data
     */
    public function dashboardData(Request $request)
    {
        try {
            $period = $request->input('period', 'month'); // day, week, month, year

            return response()->json([
                'revenue_metrics' => $this->getRevenueMetrics($period),
                'payment_stats' => $this->getPaymentStats($period),
                'student_stats' => $this->getStudentStats($period),
                'trending_data' => $this->getTrendingData($period),
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard data fetch failed', [
                'error' => $e->getMessage(),
                'period' => $request->input('period'),
            ]);

            return response()->json([
                'error' => 'Failed to fetch dashboard data'
            ], 500);
        }
    }

    /**
     * Generate revenue data
     */
    private function generateRevenueData(string $period, string $startDate, string $endDate, string $gateway): array
    {
        $query = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($gateway !== 'all') {
            $query->where('payment_method', $gateway);
        }

        // Group by period
        $groupBy = match ($period) {
            'daily' => DB::raw('DATE(paid_at)'),
            'weekly' => DB::raw('YEARWEEK(paid_at)'),
            'monthly' => DB::raw('DATE_FORMAT(paid_at, "%Y-%m")'),
            'yearly' => DB::raw('YEAR(paid_at)'),
        };

        $revenueData = $query->select([
            'payment_method as gateway',
            DB::raw('COUNT(*) as transaction_count'),
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('AVG(amount) as average_amount'),
            DB::raw($groupBy . ' as period'),
        ])
        ->groupBy('payment_method', 'period')
        ->orderBy('period')
        ->get()
        ->groupBy('gateway');

        // Calculate totals and metrics
        $totalRevenue = $revenueData->flatten()->sum('total_amount');
        $totalTransactions = $revenueData->flatten()->sum('transaction_count');
        $averageTransactionValue = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        return [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'gateway_filter' => $gateway,
            'data' => $revenueData,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'average_transaction_value' => $averageTransactionValue,
                'gateway_breakdown' => $revenueData->mapWithKeys(function ($gatewayData, $gateway) {
                    return [
                        $gateway => [
                            'total_amount' => $gatewayData->sum('total_amount'),
                            'transaction_count' => $gatewayData->sum('transaction_count'),
                            'average_amount' => $gatewayData->avg('total_amount'),
                        ]
                    ];
                }),
            ],
        ];
    }

    /**
     * Generate payment methods data
     */
    private function generatePaymentMethodsData(string $startDate, string $endDate): array
    {
        $payments = Payment::whereBetween('paid_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->get()
            ->groupBy('payment_method');

        $methodStats = [];
        foreach ($payments as $method => $methodPayments) {
            $methodStats[$method] = [
                'transaction_count' => $methodPayments->count(),
                'total_amount' => $methodPayments->sum('amount'),
                'average_amount' => $methodPayments->avg('amount'),
                'min_amount' => $methodPayments->min('amount'),
                'max_amount' => $methodPayments->max('amount'),
                'success_rate' => 100, // All completed payments here
            ];
        }

        // Include failed attempts for success rate calculation
        $attempts = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->groupBy('payment_method');

        foreach ($attempts as $method => $allAttempts) {
            $completedCount = $allAttempts->where('status', 'completed')->count();
            $totalAttemptsCount = $allAttempts->count();
            $methodStats[$method]['success_rate'] = $totalAttemptsCount > 0
                ? ($completedCount / $totalAttemptsCount) * 100
                : 0;
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'method_stats' => $methodStats,
            'summary' => [
                'total_methods' => count($methodStats),
                'most_used_method' => collect($methodStats)->sortByDesc('transaction_count')->keys()->first(),
                'highest_revenue_method' => collect($methodStats)->sortByDesc('total_amount')->keys()->first(),
            ],
        ];
    }

    /**
     * Generate student patterns data
     */
    private function generateStudentPatternsData(string $startDate, string $endDate, ?string $course, ?int $yearLevel): array
    {
        $query = Student::with(['user', 'payments']);

        if ($course) {
            $query->where('course', $course);
        }

        if ($yearLevel) {
            $query->where('year_level', $yearLevel);
        }

        $students = $query->get();

        $patterns = [];
        foreach ($students as $student) {
            $payments = $student->payments()
                ->where('status', 'completed')
                ->whereBetween('paid_at', [$startDate, $endDate])
                ->get();

            if ($payments->count() > 0) {
                $patterns[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->user->name,
                    'student_id_number' => $student->student_id,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'payment_count' => $payments->count(),
                    'total_paid' => $payments->sum('amount'),
                    'average_payment_amount' => $payments->avg('amount'),
                    'first_payment_date' => $payments->min('paid_at'),
                    'last_payment_date' => $payments->max('paid_at'),
                    'payment_methods_used' => $payments->pluck('payment_method')->unique()->values(),
                ];
            }
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'filters' => [
                'course' => $course,
                'year_level' => $yearLevel,
            ],
            'patterns' => $patterns,
            'summary' => [
                'total_students_with_payments' => count($patterns),
                'average_payments_per_student' => collect($patterns)->avg('payment_count'),
                'average_payment_amount' => collect($patterns)->avg('average_payment_amount'),
            ],
        ];
    }

    /**
     * Generate aging report data
     */
    private function generateAgingReportData(string $asOfDate, array $agingBuckets, bool $includeGraduated): array
    {
        $students = Student::with(['user', 'feeItems']);

        if (!$includeGraduated) {
            $students->where('status', 'active');
        }

        $students = $students->get();

        $agingData = [];
        $totalOutstanding = 0;

        foreach ($students as $student) {
            $outstandingBalance = $student->feeItems->sum('balance');

            if ($outstandingBalance > 0) {
                $lastPayment = $student->payments()
                    ->where('status', 'completed')
                    ->orderBy('paid_at', 'desc')
                    ->first();

                $daysSinceLastPayment = $lastPayment
                    ? $lastPayment->paid_at->diffInDays($asOfDate)
                    : 999; // No payments made

                // Determine aging bucket
                $agingBucket = '90+';
                foreach ($agingBuckets as $bucket) {
                    if ($daysSinceLastPayment <= $bucket) {
                        $agingBucket = $bucket <= 30 ? '0-30' : ($bucket <= 60 ? '31-60' : ($bucket <= 90 ? '61-90' : '90+'));
                        break;
                    }
                }

                $agingData[$agingBucket][] = [
                    'student_id' => $student->id,
                    'student_name' => $student->user->name,
                    'student_id_number' => $student->student_id,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'outstanding_balance' => $outstandingBalance,
                    'days_since_last_payment' => $daysSinceLastPayment,
                    'last_payment_date' => $lastPayment?->paid_at,
                    'status' => $student->status,
                ];

                $totalOutstanding += $outstandingBalance;
            }
        }

        // Sort aging buckets in order
        $orderedBuckets = ['0-30', '31-60', '61-90', '90+'];
        $finalAgingData = [];

        foreach ($orderedBuckets as $bucket) {
            if (isset($agingData[$bucket])) {
                $finalAgingData[$bucket] = $agingData[$bucket];
            } else {
                $finalAgingData[$bucket] = [];
            }
        }

        return [
            'as_of_date' => $asOfDate,
            'aging_buckets' => $agingBuckets,
            'include_graduated' => $includeGraduated,
            'aging_data' => $finalAgingData,
            'summary' => [
                'total_outstanding_balance' => $totalOutstanding,
                'total_students_with_balance' => collect($finalAgingData)->flatten()->count(),
                'average_days_since_payment' => collect($finalAgingData)->flatten()->avg('days_since_last_payment'),
                'bucket_summary' => collect($finalAgingData)->mapWithKeys(function ($bucket, $key) {
                    return [
                        $key => [
                            'count' => count($bucket),
                            'total_balance' => collect($bucket)->sum('outstanding_balance'),
                            'average_balance' => count($bucket) > 0 ? collect($bucket)->avg('outstanding_balance') : 0,
                        ]
                    ];
                }),
            ],
        ];
    }

    /**
     * Generate course revenue data
     */
    private function generateCourseRevenueData(string $schoolYear, string $semester): array
    {
        // This would need to be implemented based on your specific course/fee structure
        // This is a template that shows the structure

        $courses = DB::table('students')
            ->select('course', DB::raw('COUNT(*) as student_count'))
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->groupBy('course')
            ->get();

        $courseData = [];
        foreach ($courses as $course) {
            // Calculate revenue for this course
            $revenue = Payment::join('students', 'payments.student_id', '=', 'students.id')
                ->where('students.course', $course->course)
                ->where('students.school_year', $schoolYear)
                ->where('students.semester', $semester)
                ->where('payments.status', 'completed')
                ->sum('payments.amount');

            $courseData[] = [
                'course' => $course->course,
                'student_count' => $course->student_count,
                'total_revenue' => $revenue,
                'revenue_per_student' => $course->student_count > 0 ? $revenue / $course->student_count : 0,
            ];
        }

        return [
            'school_year' => $schoolYear,
            'semester' => $semester,
            'course_data' => $courseData,
            'summary' => [
                'total_students' => $courses->sum('student_count'),
                'total_revenue' => collect($courseData)->sum('total_revenue'),
                'average_revenue_per_student' => collect($courseData)->avg('revenue_per_student'),
            ],
        ];
    }

    // Helper methods for chart data preparation
    private function prepareRevenueChartData(array $data): array
    {
        return [
            'labels' => $data['data']->keys()->toArray(),
            'datasets' => $data['data']->map(function ($gatewayData) {
                return [
                    'label' => ucfirst($gatewayData->first()->gateway),
                    'data' => $gatewayData->pluck('total_amount')->toArray(),
                    'backgroundColor' => [
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                    ],
                    'borderColor' => [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                    ],
                ];
            })->values()->toArray(),
        ];
    }

    private function preparePaymentMethodsChartData(array $data): array
    {
        $labels = array_keys($data['method_stats']);
        $values = array_column($data['method_stats'], 'total_amount');

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $values,
                'backgroundColor' => [
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                ],
                'borderColor' => [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                ],
            ]],
        ];
    }

    private function prepareStudentPatternsChartData(array $data): array
    {
        // Implementation depends on specific patterns you want to visualize
        return [
            'labels' => ['Course A', 'Course B', 'Course C', 'Course D'],
            'datasets' => [
                [
                    'label' => 'Average Payment Amount',
                    'data' => [1000, 1500, 1200, 1800],
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                ],
            ],
        ];
    }

    private function prepareAgingChartData(array $data): array
    {
        return [
            'labels' => array_keys($data['aging_data']),
            'datasets' => [[
                'label' => 'Total Outstanding Balance',
                'data' => collect($data['aging_data'])->map(function ($bucket) {
                    return collect($bucket)->sum('outstanding_balance');
                })->toArray(),
                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                'borderColor' => 'rgba(255, 99, 132, 1)',
            ]],
        ];
    }

    private function prepareCourseRevenueChartData(array $data): array
    {
        return [
            'labels' => collect($data['course_data'])->pluck('course')->toArray(),
            'datasets' => [
                [
                    'label' => 'Total Revenue',
                    'data' => collect($data['course_data'])->pluck('total_revenue')->toArray(),
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'borderColor' => 'rgba(75, 192, 192, 1)',
                ],
                [
                    'label' => 'Student Count',
                    'data' => collect($data['course_data'])->pluck('student_count')->toArray(),
                    'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                    'borderColor' => 'rgba(54, 162, 235, 1)',
                ],
            ],
        ];
    }

    // Helper methods for dashboard data
    private function getAvailableReports(): array
    {
        return [
            'revenue' => [
                'name' => 'Revenue Report',
                'description' => 'Daily, weekly, monthly, and yearly revenue analysis',
                'icon' => 'dollar-sign',
                'color' => 'green',
            ],
            'payment_methods' => [
                'name' => 'Payment Methods Breakdown',
                'description' => 'Analysis of payment method usage and performance',
                'icon' => 'credit-card',
                'color' => 'blue',
            ],
            'student_patterns' => [
                'name' => 'Student Payment Patterns',
                'description' => 'Student payment behavior and trends analysis',
                'icon' => 'users',
                'color' => 'purple',
            ],
            'aging' => [
                'name' => 'Aging Report',
                'description' => 'Outstanding balance aging analysis',
                'icon' => 'clock',
                'color' => 'orange',
            ],
            'course_revenue' => [
                'name' => 'Course Revenue Analysis',
                'description' => 'Revenue analysis by course and program',
                'icon' => 'graduation-cap',
                'color' => 'indigo',
            ],
        ];
    }

    private function getRecentExports(): array
    {
        // This would come from a database table tracking report exports
        return [
            [
                'report_type' => 'Revenue',
                'generated_by' => 'Admin User',
                'generated_at' => now()->subMinutes(15)->format('M d, Y h:i A'),
                'format' => 'PDF',
                'file_size' => '2.4 MB',
            ],
            [
                'report_type' => 'Aging Report',
                'generated_by' => 'Accounting Staff',
                'generated_at' => now()->subHours(2)->format('M d, Y h:i A'),
                'format' => 'Excel',
                'file_size' => '1.8 MB',
            ],
        ];
    }

    private function getSystemStats(): array
    {
        return [
            'total_payments_today' => Payment::whereDate('paid_at', today())->count(),
            'revenue_today' => Payment::whereDate('paid_at', today())->sum('amount'),
            'active_students' => Student::where('status', 'active')->count(),
            'pending_transactions' => Transaction::where('status', 'pending')->count(),
        ];
    }

    private function getRevenueMetrics(string $period): array
    {
        $dateRange = match ($period) {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
        };

        $revenue = Payment::where('status', 'completed')
            ->whereBetween('paid_at', $dateRange)
            ->sum('amount');

        return [
            'current' => $revenue,
            'previous' => $revenue * 0.85, // Example: previous period was 85% of current
            'growth' => 15.5,
        ];
    }

    private function getPaymentStats(string $period): array
    {
        $dateRange = match ($period) {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
        };

        $completed = Payment::where('status', 'completed')
            ->whereBetween('paid_at', $dateRange)
            ->count();

        $failed = Payment::where('status', 'failed')
            ->whereBetween('created_at', $dateRange)
            ->count();

        $total = $completed + $failed;

        return [
            'completed' => $completed,
            'failed' => $failed,
            'success_rate' => $total > 0 ? ($completed / $total) * 100 : 0,
        ];
    }

    private function getStudentStats(string $period): array
    {
        return [
            'active_students' => Student::where('status', 'active')->count(),
            'new_registrations' => Student::whereBetween('created_at', [now()->startOfMonth(), now()])->count(),
            'students_with_balance' => Student::whereHas('studentFeeItems', function ($query) {
                $query->where('balance', '>', 0);
            })->count(),
        ];
    }

    private function getTrendingData(string $period): array
    {
        // Generate sample trending data for the last 7 days
        $labels = [];
        $revenueData = [];
        $transactionData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('M d');

            $revenueData[] = Payment::whereDate('paid_at', $date)
                ->where('status', 'completed')
                ->sum('amount');

            $transactionData[] = Payment::whereDate('paid_at', $date)
                ->where('status', 'completed')
                ->count();
        }

        return [
            'labels' => $labels,
            'revenue' => $revenueData,
            'transactions' => $transactionData,
        ];
    }
}