<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Subject::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('course', 'like', "%{$search}%");
            });
        }

        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        $subjects = $query->orderBy('year_level')
                          ->orderBy('semester')
                          ->orderBy('code')
                          ->paginate(15)
                          ->withQueryString();

        return Inertia::render('Subjects/Index', [
            'subjects' => $subjects,
            'filters' => $request->only(['search', 'year_level', 'semester', 'course']),
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters' => ['1st Sem', '2nd Sem', 'Summer'],
            'courses' => ['BS Computer Science', 'BS Information Technology', 'BS Accountancy'],
        ]);
    }

    public function create()
    {
        return Inertia::render('Subjects/Create', [
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters' => ['1st Sem', '2nd Sem', 'Summer'],
            'courses' => ['BS Computer Science', 'BS Information Technology', 'BS Accountancy'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:subjects',
            'name' => 'required|string|max:255',
            'units' => 'required|integer|min:1|max:10',
            'price_per_unit' => 'required|numeric|min:0',
            'year_level' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'course' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'has_lab' => 'boolean',
            'lab_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        Subject::create($validated);

        return redirect()->route('subjects.index')
            ->with('success', 'Subject created successfully!');
    }

    public function edit(Subject $subject)
    {
        return Inertia::render('Subjects/Edit', [
            'subject' => $subject,
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'semesters' => ['1st Sem', '2nd Sem', 'Summer'],
            'courses' => ['BS Computer Science', 'BS Information Technology', 'BS Accountancy'],
        ]);
    }

    public function update(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:subjects,code,' . $subject->id,
            'name' => 'required|string|max:255',
            'units' => 'required|integer|min:1|max:10',
            'price_per_unit' => 'required|numeric|min:0',
            'year_level' => 'required|string|max:50',
            'semester' => 'required|string|max:50',
            'course' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'has_lab' => 'boolean',
            'lab_fee' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $subject->update($validated);

        return redirect()->route('subjects.index')
            ->with('success', 'Subject updated successfully!');
    }

    public function destroy(Subject $subject)
    {
        if ($subject->enrollments()->exists()) {
            return redirect()->route('subjects.index')
                ->with('error', 'Cannot delete subject with existing enrollments.');
        }

        $subject->delete();

        return redirect()->route('subjects.index')
            ->with('success', 'Subject deleted successfully!');
    }

    /**
     * Enroll students in subjects
     */
    public function enrollStudents(Request $request, Subject $subject)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'school_year' => 'required|string',
        ]);

        $enrolled = 0;
        foreach ($validated['user_ids'] as $userId) {
            StudentEnrollment::firstOrCreate([
                'user_id' => $userId,
                'subject_id' => $subject->id,
                'school_year' => $validated['school_year'],
                'semester' => $subject->semester,
            ]);
            $enrolled++;
        }

        return redirect()->back()
            ->with('success', "Successfully enrolled {$enrolled} student(s) in {$subject->name}!");
    }
}