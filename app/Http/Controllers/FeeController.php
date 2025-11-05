<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeeController extends Controller
{
    /**
     * Display a listing of the fees.
     */
    public function index(Request $request)
    {
        $query = Fee::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Filter by year level
        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        // Filter by semester
        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        // Filter by school year
        if ($request->filled('school_year')) {
            $query->where('school_year', $request->school_year);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $fees = $query->orderBy('school_year', 'desc')
                      ->orderBy('semester')
                      ->orderBy('year_level')
                      ->paginate(15)
                      ->withQueryString();

        return Inertia::render('Fees/Index', [
            'fees' => $fees,
            'filters' => $request->only(['search', 'year_level', 'semester', 'school_year', 'is_active']),
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters' => ['1st Sem', '2nd Sem', 'Summer'],
            'categories' => ['Tuition', 'Laboratory', 'Miscellaneous', 'Library', 'Athletic', 'Other'],
        ]);
    }

    /**
     * Show the form for creating a new fee.
     */
    public function create()
    {
        return Inertia::render('Fees/Create', [
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters' => ['1st Sem', '2nd Sem', 'Summer'],
            'categories' => ['Tuition', 'Laboratory', 'Miscellaneous', 'Library', 'Athletic', 'Other'],
        ]);
    }

    /**
     * Store a newly created fee in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'year_level' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        // Generate unique code
        $validated['code'] = Fee::generateCode(
            $validated['category'],
            $validated['school_year'],
            $validated['semester']
        );

        Fee::create($validated);

        return redirect()->route('fees.index')
            ->with('success', 'Fee created successfully!');
    }

    /**
     * Display the specified fee.
     */
    public function show(Fee $fee)
    {
        $fee->load(['transactions' => function ($query) {
            $query->with('user')->latest()->take(10);
        }]);

        return Inertia::render('Fees/Show', [
            'fee' => $fee,
        ]);
    }

    /**
     * Show the form for editing the specified fee.
     */
    public function edit(Fee $fee)
    {
        return Inertia::render('Fees/Edit', [
            'fee' => $fee,
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters' => ['1st Sem', '2nd Sem', 'Summer'],
            'categories' => ['Tuition', 'Laboratory', 'Miscellaneous', 'Library', 'Athletic', 'Other'],
        ]);
    }

    /**
     * Update the specified fee in storage.
     */
    public function update(Request $request, Fee $fee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'year_level' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'school_year' => 'required|string|max:20',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $fee->update($validated);

        return redirect()->route('fees.index')
            ->with('success', 'Fee updated successfully!');
    }

    /**
     * Remove the specified fee from storage.
     */
    public function destroy(Fee $fee)
    {
        // Check if fee has associated transactions
        if ($fee->transactions()->exists()) {
            return redirect()->route('fees.index')
                ->with('error', 'Cannot delete fee with existing transactions. Deactivate it instead.');
        }

        $fee->delete();

        return redirect()->route('fees.index')
            ->with('success', 'Fee deleted successfully!');
    }

    /**
     * Toggle fee active status
     */
    public function toggleStatus(Fee $fee)
    {
        $fee->update(['is_active' => !$fee->is_active]);

        return redirect()->back()
            ->with('success', 'Fee status updated successfully!');
    }

    /**
     * Assign fees to students
     */
    public function assignToStudents(Request $request)
    {
        $validated = $request->validate([
            'fee_ids' => 'required|array',
            'fee_ids.*' => 'exists:fees,id',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $fees = Fee::whereIn('id', $validated['fee_ids'])->get();
        $users = \App\Models\User::whereIn('id', $validated['user_ids'])->get();

        $created = 0;
        foreach ($users as $user) {
            foreach ($fees as $fee) {
                // Create transaction for each fee
                $transaction = \App\Models\Transaction::create([
                    'user_id' => $user->id,
                    'fee_id' => $fee->id,
                    'reference' => 'FEE-' . strtoupper(\Illuminate\Support\Str::random(8)),
                    'kind' => 'charge',
                    'type' => $fee->category,
                    'year' => explode('-', $fee->school_year)[0],
                    'semester' => $fee->semester,
                    'amount' => $fee->amount,
                    'status' => 'pending',
                    'meta' => [
                        'fee_code' => $fee->code,
                        'fee_name' => $fee->name,
                        'auto_assigned' => true,
                    ],
                ]);
                $created++;
            }
        }

        return redirect()->back()
            ->with('success', "Successfully assigned {$created} fees to " . count($users) . " student(s)!");
    }
}