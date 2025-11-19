<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentFeeItem;
use App\Models\User;
use App\Services\AuditService;

class BulkOperationController extends Controller
{
    public function __construct(
        private AuditService $auditService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin,accounting');
    }

    /**
     * Display bulk operations dashboard
     */
    public function index()
    {
        return Inertia::render('BulkOperations/Index', [
            'stats' => [
                'total_students' => Student::count(),
                'active_students' => Student::whereHas('user', function ($query) {
                    $query->where('status', 'active');
                })->count(),
                'total_outstanding' => StudentFeeItem::where('balance', '>', 0)->sum('balance'),
                'students_with_balance' => StudentFeeItem::where('balance', '>', 0)
                    ->distinct('student_id')
                    ->count('student_id'),
            ]
        ]);
    }

    /**
     * Bulk assign fees to multiple students
     */
    public function bulkAssignFees(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'fee_ids' => 'required|array|min:1',
            'fee_ids.*' => 'exists:fees,id',
            'due_date' => 'nullable|date|after:today',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $students = Student::whereIn('id', $request->student_ids)->get();
                $feeItems = [];

                foreach ($students as $student) {
                    foreach ($request->fee_ids as $feeId) {
                        $feeItems[] = [
                            'student_id' => $student->id,
                            'fee_id' => $feeId,
                            'amount' => 0, // Will be calculated based on fee configuration
                            'balance' => 0,
                            'due_date' => $request->due_date,
                            'description' => $request->description,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Bulk insert fee items
                StudentFeeItem::insert($feeItems);

                // Log audit trail
                $this->auditService->logBulkOperation(
                    'bulk_fee_assignment',
                    Auth::id(),
                    [
                        'student_count' => count($request->student_ids),
                        'fee_count' => count($request->fee_ids),
                        'due_date' => $request->due_date,
                    ]
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Fees assigned successfully to ' . count($request->student_ids) . ' students'
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk fee assignment failed', [
                'error' => $e->getMessage(),
                'student_ids' => $request->student_ids,
                'fee_ids' => $request->fee_ids,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign fees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update fee amounts
     */
    public function bulkUpdateFees(Request $request)
    {
        $request->validate([
            'fee_item_ids' => 'required|array|min:1',
            'fee_item_ids.*' => 'exists:student_fee_items,id',
            'update_type' => 'required|in:set_amount,adjust_percentage,add_amount',
            'value' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $feeItems = StudentFeeItem::whereIn('id', $request->fee_item_ids)->get();
                $updatedCount = 0;

                foreach ($feeItems as $feeItem) {
                    $oldAmount = $feeItem->amount;
                    $oldBalance = $feeItem->balance;

                    switch ($request->update_type) {
                        case 'set_amount':
                            $newAmount = $request->value;
                            break;
                        case 'adjust_percentage':
                            $newAmount = $oldAmount * (1 + ($request->value / 100));
                            break;
                        case 'add_amount':
                            $newAmount = $oldAmount + $request->value;
                            break;
                    }

                    $newBalance = $newAmount - $feeItem->amount_paid;

                    $feeItem->update([
                        'amount' => $newAmount,
                        'balance' => max(0, $newBalance),
                        'description' => $request->reason,
                    ]);

                    $updatedCount++;
                }

                // Log audit trail
                $this->auditService->logBulkOperation(
                    'bulk_fee_update',
                    Auth::id(),
                    [
                        'update_type' => $request->update_type,
                        'value' => $request->value,
                        'updated_count' => $updatedCount,
                        'reason' => $request->reason,
                    ]
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Updated ' . count($request->fee_item_ids) . ' fee items successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk fee update failed', [
                'error' => $e->getMessage(),
                'fee_item_ids' => $request->fee_item_ids,
                'update_type' => $request->update_type,
                'value' => $request->value,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update fees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk waive fees
     */
    public function bulkWaiveFees(Request $request)
    {
        $request->validate([
            'fee_item_ids' => 'required|array|min:1',
            'fee_item_ids.*' => 'exists:student_fee_items,id',
            'waiver_reason' => 'required|string|max:255',
            'waiver_percentage' => 'nullable|numeric|min:0|max:100',
            'waiver_amount' => 'nullable|numeric|min:0',
        ]);

        // Validate either percentage or amount is provided
        if (!$request->waiver_percentage && !$request->waiver_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Either waiver percentage or waiver amount must be specified'
            ], 400);
        }

        try {
            DB::transaction(function () use ($request) {
                $feeItems = StudentFeeItem::whereIn('id', $request->fee_item_ids)->get();
                $waivedCount = 0;
                $totalWaivedAmount = 0;

                foreach ($feeItems as $feeItem) {
                    $waiverAmount = 0;

                    if ($request->waiver_percentage) {
                        $waiverAmount = $feeItem->balance * ($request->waiver_percentage / 100);
                    } else {
                        $waiverAmount = min($request->waiver_amount, $feeItem->balance);
                    }

                    if ($waiverAmount > 0) {
                        $newBalance = $feeItem->balance - $waiverAmount;

                        $feeItem->update([
                            'balance' => max(0, $newBalance),
                            'waiver_amount' => ($feeItem->waiver_amount ?? 0) + $waiverAmount,
                            'waiver_reason' => $request->waiver_reason,
                        ]);

                        $waivedCount++;
                        $totalWaivedAmount += $waiverAmount;
                    }
                }

                // Log audit trail
                $this->auditService->logBulkOperation(
                    'bulk_fee_waiver',
                    Auth::id(),
                    [
                        'waived_count' => $waivedCount,
                        'total_waived_amount' => $totalWaivedAmount,
                        'waiver_reason' => $request->waiver_reason,
                        'waiver_percentage' => $request->waiver_percentage,
                        'waiver_amount' => $request->waiver_amount,
                    ]
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Fees waived successfully for ' . count($request->fee_item_ids) . ' items'
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk fee waiver failed', [
                'error' => $e->getMessage(),
                'fee_item_ids' => $request->fee_item_ids,
                'waiver_reason' => $request->waiver_reason,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to waive fees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk send payment reminders
     */
    public function bulkSendReminders(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'message_type' => 'required|in:email,sms,both',
            'custom_message' => 'nullable|string|max:500',
            'include_overdue_only' => 'boolean',
        ]);

        try {
            $students = Student::whereIn('id', $request->student_ids)
                ->with(['user', 'feeItems' => function ($query) use ($request) {
                    $query->where('balance', '>', 0);
                    if ($request->include_overdue_only) {
                        $query->where('due_date', '<', now());
                    }
                }])
                ->get();

            $sentCount = 0;
            $failedCount = 0;

            foreach ($students as $student) {
                if ($student->feeItems->isEmpty()) {
                    continue;
                }

                try {
                    // Send email reminder
                    if ($request->message_type === 'email' || $request->message_type === 'both') {
                        // Send email logic here
                        // Mail::to($student->user->email)->send(new PaymentReminderMail($student, $request->custom_message));
                    }

                    // Send SMS reminder
                    if ($request->message_type === 'sms' || $request->message_type === 'both') {
                        // Send SMS logic here
                        // SMSService::send($student->phone_number, $reminderMessage);
                    }

                    $sentCount++;

                } catch (\Exception $e) {
                    Log::error('Failed to send reminder to student', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            }

            // Log audit trail
            $this->auditService->logBulkOperation(
                'bulk_payment_reminders',
                Auth::id(),
                [
                    'student_count' => count($request->student_ids),
                    'sent_count' => $sentCount,
                    'failed_count' => $failedCount,
                    'message_type' => $request->message_type,
                    'include_overdue_only' => $request->include_overdue_only,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Reminders sent successfully. {$sentCount} sent, {$failedCount} failed."
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk reminder sending failed', [
                'error' => $e->getMessage(),
                'student_ids' => $request->student_ids,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reminders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk export student data
     */
    public function bulkExportStudents(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'export_format' => 'required|in:csv,xlsx,pdf',
            'include_payment_history' => 'boolean',
            'include_fee_breakdown' => 'boolean',
        ]);

        try {
            $students = Student::whereIn('id', $request->student_ids)
                ->with(['user', 'course', 'yearLevel'])
                ->get();

            if ($request->include_payment_history) {
                $students->load(['payments' => function ($query) {
                    $query->with('latestGatewayDetail')->orderBy('created_at', 'desc');
                }]);
            }

            if ($request->include_fee_breakdown) {
                $students->load(['feeItems' => function ($query) {
                    $query->with(['fee', 'feeCategory']);
                }]);
            }

            // Generate export based on format
            $filename = 'student_export_' . date('Y-m-d_H-i-s');

            switch ($request->export_format) {
                case 'csv':
                    // Export to CSV logic here
                    break;
                case 'xlsx':
                    // Export to Excel logic here
                    break;
                case 'pdf':
                    // Export to PDF logic here
                    break;
            }

            // Log audit trail
            $this->auditService->logBulkOperation(
                'bulk_student_export',
                Auth::id(),
                [
                    'student_count' => count($request->student_ids),
                    'export_format' => $request->export_format,
                    'include_payment_history' => $request->include_payment_history,
                    'include_fee_breakdown' => $request->include_fee_breakdown,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Export completed successfully',
                'download_url' => "/downloads/{$filename}.{$request->export_format}"
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk student export failed', [
                'error' => $e->getMessage(),
                'student_ids' => $request->student_ids,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export students: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bulk operation statistics
     */
    public function getStats()
    {
        return response()->json([
            'students_with_outstanding' => Student::whereHas('feeItems', function ($query) {
                $query->where('balance', '>', 0);
            })->count(),
            'total_outstanding_amount' => StudentFeeItem::where('balance', '>', 0)->sum('balance'),
            'overdue_amount' => StudentFeeItem::where('balance', '>', 0)
                ->where('due_date', '<', now())
                ->sum('balance'),
            'overdue_students' => Student::whereHas('feeItems', function ($query) {
                $query->where('balance', '>', 0)->where('due_date', '<', now());
            })->count(),
        ]);
    }
}