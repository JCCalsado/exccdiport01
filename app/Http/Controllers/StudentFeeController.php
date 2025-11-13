<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentFeeController extends Controller
{
    /**
     * Display listing of students for fee management
     */
    public function index(Request $request)
    {
        $query = User::with(['student', 'account'])
            ->where('role', 'student');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        // Filter by course
        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        // Filter by year level
        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->paginate(15)->withQueryString();

        // Get filter options
        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course');

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $statuses = [
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_GRADUATED => 'Graduated',
            User::STATUS_DROPPED => 'Dropped',
        ];

        return Inertia::render('StudentFees/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'course', 'year_level', 'status']),
            'courses' => $courses,
            'yearLevels' => $yearLevels,
            'statuses' => $statuses,
        ]);
    }

    /**
     * Show create assessment form
     */
    public function create(Request $request)
    {
        // If this is an AJAX request for getting student data
        if ($request->has('get_data') && $request->has('student_id')) {
            $student = User::where('role', 'student')->findOrFail($request->student_id);
            
            // Get subjects for this student
            $subjects = Subject::active()
                ->where('course', $student->course)
                ->where('year_level', $student->year_level)
                ->get()
                ->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'code' => $subject->code,
                        'name' => $subject->name,
                        'units' => $subject->units,
                        'price_per_unit' => $subject->price_per_unit,
                        'has_lab' => $subject->has_lab,
                        'lab_fee' => $subject->lab_fee,
                        'total_cost' => $subject->total_cost,
                    ];
                });

            // Get fees
            $fees = Fee::active()
                ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
                ->get()
                ->map(function ($fee) {
                    return [
                        'id' => $fee->id,
                        'name' => $fee->name,
                        'category' => $fee->category,
                        'amount' => $fee->amount,
                    ];
                });

            return response()->json([
                'subjects' => $subjects,
                'fees' => $fees,
            ]);
        }

        // Get all active students for selection
        $students = User::where('role', 'student')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'student_id' => $user->student_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'course' => $user->course,
                    'year_level' => $user->year_level,
                    'status' => $user->status,
                ];
            });

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];
        $currentYear = now()->year;
        $schoolYears = [
            "{$currentYear}-" . ($currentYear + 1),
            ($currentYear - 1) . "-{$currentYear}",
        ];

        return Inertia::render('StudentFees/Create', [
            'students' => $students,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Store new assessment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
            'subjects' => 'required|array|min:1',
            'subjects.*.id' => 'required|exists:subjects,id',
            'subjects.*.units' => 'required|numeric|min:0',
            'subjects.*.amount' => 'required|numeric|min:0',
            'other_fees' => 'nullable|array',
            'other_fees.*.id' => 'required|exists:fees,id',
            'other_fees.*.amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Calculate tuition fee
            $tuitionFee = collect($validated['subjects'])->sum('amount');
            
            // Calculate other fees
            $otherFeesTotal = isset($validated['other_fees']) 
                ? collect($validated['other_fees'])->sum('amount') 
                : 0;

            // Create assessment
            $assessment = StudentAssessment::create([
                'user_id' => $validated['user_id'],
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'school_year' => $validated['school_year'],
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'subjects' => $validated['subjects'],
                'fee_breakdown' => $validated['other_fees'] ?? [],
                'created_by' => auth()->id(),
                'status' => 'active',
            ]);

            // Create transactions for each subject
            foreach ($validated['subjects'] as $subject) {
                Transaction::create([
                    'user_id' => $validated['user_id'],
                    'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                    'kind' => 'charge',
                    'type' => 'Tuition',
                    'year' => explode('-', $validated['school_year'])[0],
                    'semester' => $validated['semester'],
                    'amount' => $subject['amount'],
                    'status' => 'pending',
                    'meta' => [
                        'assessment_id' => $assessment->id,
                        'subject_id' => $subject['id'],
                        'description' => 'Tuition Fee - Subject',
                    ],
                ]);
            }

            // Create transactions for other fees
            if (isset($validated['other_fees'])) {
                foreach ($validated['other_fees'] as $fee) {
                    $feeModel = Fee::find($fee['id']);
                    Transaction::create([
                        'user_id' => $validated['user_id'],
                        'fee_id' => $fee['id'],
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind' => 'charge',
                        'type' => $feeModel->category,
                        'year' => explode('-', $validated['school_year'])[0],
                        'semester' => $validated['semester'],
                        'amount' => $fee['amount'],
                        'status' => 'pending',
                        'meta' => [
                            'assessment_id' => $assessment->id,
                            'fee_code' => $feeModel->code,
                            'fee_name' => $feeModel->name,
                        ],
                    ]);
                }
            }

            // Recalculate student balance
            $user = User::find($validated['user_id']);
            \App\Services\AccountService::recalculate($user);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $validated['user_id'])
                ->with('success', 'Student fee assessment created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create assessment: ' . $e->getMessage()]);
        }
    }

    /**
     * Show student fee details
     */
    public function show($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        // Get latest assessment
        $latestAssessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Get all transactions
        $transactions = Transaction::where('user_id', $userId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get payments
        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->orderBy('paid_at', 'desc')
            ->get();

        // Calculate fee breakdown
        $feeBreakdown = $transactions->where('kind', 'charge')
            ->groupBy('type')
            ->map(function ($group) {
                return [
                    'category' => $group->first()->type,
                    'total' => $group->sum('amount'),
                    'items' => $group->count(),
                ];
            });

        return Inertia::render('StudentFees/Show', [
            'student' => $student,
            'assessment' => $latestAssessment,
            'transactions' => $transactions,
            'payments' => $payments,
            'feeBreakdown' => $feeBreakdown->values(),
        ]);
    }

    /**
     * Store payment for student
     */
    public function storePayment(Request $request, $userId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'description' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
        ]);

        $student = User::with('student')->where('role', 'student')->findOrFail($userId);

        if (!$student->student) {
            return back()->withErrors(['error' => 'Student record not found.']);
        }

        DB::beginTransaction();
        try {
            // Create payment record
            $payment = Payment::create([
                'student_id' => $student->student->id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                'description' => $validated['description'] ?? 'Payment',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => $validated['payment_date'] ?? now(),
            ]);

            // Create transaction record
            Transaction::create([
                'user_id' => $userId,
                'reference' => $payment->reference_number,
                'payment_channel' => $validated['payment_method'],
                'kind' => 'payment',
                'type' => 'Payment',
                'amount' => $validated['amount'],
                'status' => 'paid',
                'paid_at' => $payment->paid_at,
                'meta' => [
                    'payment_id' => $payment->id,
                    'description' => $validated['description'] ?? 'Payment',
                ],
            ]);

            // Recalculate balance
            \App\Services\AccountService::recalculate($student);

            DB::commit();

            return back()->with('success', 'Payment recorded successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to record payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Edit assessment
     */
    public function edit($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $subjects = Subject::active()
            ->where('course', $student->course)
            ->where('year_level', $student->year_level)
            ->get();

        $fees = Fee::active()
            ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
            ->get();

        return Inertia::render('StudentFees/Edit', [
            'student' => $student,
            'assessment' => $assessment,
            'subjects' => $subjects,
            'fees' => $fees,
        ]);
    }

    /**
     * Export assessment to PDF
     */
    public function exportPdf($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $transactions = Transaction::where('user_id', $userId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->orderBy('paid_at', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.student-assessment', [
            'student' => $student,
            'assessment' => $assessment,
            'transactions' => $transactions,
            'payments' => $payments,
        ]);

        return $pdf->download("assessment-{$student->student_id}.pdf");
    }

    /**
     * Show create student form
     */
    public function createStudent()
    {
        return Inertia::render('StudentFees/CreateStudent');
    }

    /**
     * Store new student
     */
    public function storeStudent(Request $request)
    {
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'birthday' => 'required|date',
            'year_level' => 'required|string|max:50',
            'course' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'student_id' => 'nullable|string|unique:users,student_id',
        ]);

        DB::beginTransaction();
        try {
            // Generate student ID if not provided
            if (empty($validated['student_id'])) {
                $year = now()->year;
                $lastStudent = User::where('student_id', 'like', "{$year}-%")
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastStudent) {
                    $lastNumber = intval(substr($lastStudent->student_id, -4));
                    $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                } else {
                    $newNumber = '0001';
                }

                $validated['student_id'] = "{$year}-{$newNumber}";
            }

            // Create user
            $user = User::create([
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'password' => Hash::make('password'), // Default password
                'birthday' => $validated['birthday'],
                'year_level' => $validated['year_level'],
                'course' => $validated['course'],
                'address' => $validated['address'],
                'phone' => $validated['phone'],
                'student_id' => $validated['student_id'],
                'status' => User::STATUS_ACTIVE,
                'role' => 'student',
            ]);

            // Create account
            $user->account()->create(['balance' => 0]);

            // Create student record
            Student::create([
                'user_id' => $user->id,
                'student_id' => $validated['student_id'],
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'course' => $validated['course'],
                'year_level' => $validated['year_level'],
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'total_balance' => 0,
                'status' => 'enrolled',
            ]);

            DB::commit();

            return redirect()
                ->route('student-fees.index')
                ->with('success', 'Student added successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to add student: ' . $e->getMessage()]);
        }
    }
}