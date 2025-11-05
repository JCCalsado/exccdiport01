<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AccountingDashboardController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDashboardController;

/*
|--------------------------------------------------------------------------
| TEST ROUTES (Remove in production)
|--------------------------------------------------------------------------
*/

// Quick test route
Route::get('/test-route', fn () => 'Test route is working!');

// Session test
Route::get('/test-session', function () {
    session(['web_test' => 'web_value']);
    $sessionId = session()->getId();
    $sessionFile = storage_path('framework/sessions/' . $sessionId);

    return response()->json([
        'session_value'       => session('web_test'),
        'session_id'          => $sessionId,
        'session_file_exists' => file_exists($sessionFile),
        'csrf_token'          => csrf_token(),
    ]);
});

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    /*
    |-------------------------------
    | DASHBOARD
    |-------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |-------------------------------
    | PROFILE / SETTINGS
    |-------------------------------
    */
    Route::prefix('/settings')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile-picture', [ProfileController::class, 'updateProfilePicture'])->name('profile.update-picture');
        Route::delete('/profile-picture', [ProfileController::class, 'removeProfilePicture'])->name('profile.remove-picture');
    });

    /*
    |-------------------------------
    | STUDENT ROUTES (Student Only)
    |-------------------------------
    */
    Route::middleware('role:student')->group(function () {
        Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
        Route::get('/student/account', [StudentAccountController::class, 'index'])->name('student.account');
        Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
        Route::get('/my-profile', [StudentController::class, 'studentProfile'])->name('my-profile');
    });

    /*
    |-------------------------------
    | STUDENT MANAGEMENT (Student + Accounting + Admin)
    |-------------------------------
    */
    Route::middleware('role:student,accounting,admin')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    });

    /*
    |-------------------------------
    | STUDENT MANAGEMENT (Accounting + Admin Only)
    |-------------------------------
    */
    Route::middleware('role:accounting,admin')->group(function () {
        Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
        Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
        Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
        Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
        Route::post('/students/{student}/payments', [StudentController::class, 'storePayment'])->name('students.payments.store');
    });

    /*
    |-------------------------------
    | STUDENT FEE MANAGEMENT (Accounting + Admin)
    |-------------------------------
    */
    // Route::middleware('role:accounting,admin')->prefix('student-fees')->name('student-fees.')->group(function () {
    //     Route::get('/', [App\Http\Controllers\StudentFeeController::class, 'index'])->name('index');
    //     Route::get('/create', [App\Http\Controllers\StudentFeeController::class, 'create'])->name('create');
    //     Route::post('/', [App\Http\Controllers\StudentFeeController::class, 'store'])->name('store');
    //     Route::get('/{user}', [App\Http\Controllers\StudentFeeController::class, 'show'])->name('show');
    //     Route::get('/{user}/edit', [App\Http\Controllers\StudentFeeController::class, 'edit'])->name('edit');
    //     Route::post('/{user}/payments', [App\Http\Controllers\StudentFeeController::class, 'storePayment'])->name('payments.store');
    //     Route::get('/{user}/export-pdf', [App\Http\Controllers\StudentFeeController::class, 'exportPdf'])->name('export-pdf');
    // });

    Route::middleware('role:accounting,admin')->prefix('student-fees')->name('student-fees.')->group(function () {
        Route::get('/', [App\Http\Controllers\StudentFeeController::class, 'index'])->name('index');
        
        // Student creation routes
        Route::get('/create-student', [App\Http\Controllers\StudentFeeController::class, 'createStudent'])->name('create-student');
        Route::post('/store-student', [App\Http\Controllers\StudentFeeController::class, 'storeStudent'])->name('store-student');
        
        // Assessment routes
        Route::get('/create', [App\Http\Controllers\StudentFeeController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\StudentFeeController::class, 'store'])->name('store');
        Route::get('/{user}', [App\Http\Controllers\StudentFeeController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [App\Http\Controllers\StudentFeeController::class, 'edit'])->name('edit');
        Route::put('/{user}', [App\Http\Controllers\StudentFeeController::class, 'update'])->name('update');
        Route::post('/{user}/payments', [App\Http\Controllers\StudentFeeController::class, 'storePayment'])->name('payments.store');
        Route::get('/{user}/export-pdf', [App\Http\Controllers\StudentFeeController::class, 'exportPdf'])->name('export-pdf');
    });

    /*<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});

    |-------------------------------
    | TRANSACTIONS (Student + Admin)
    |-------------------------------
    */
    Route::middleware('role:student,admin,accounting')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/download', [TransactionController::class, 'download'])->name('transactions.download');
        Route::post('/account/pay-now', [TransactionController::class, 'payNow'])->name('account.pay-now');
    });

    /*
    |-------------------------------
    | TRANSACTIONS (Admin Only)
    |-------------------------------
    */

    Route::middleware(['auth', 'role:admin'])->group(function () {
        Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    });

    Route::middleware('role:admin,accounting')->group(function () {
        Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
    });

    /*
    |-------------------------------
    | ACCOUNTING DASHBOARD
    |-------------------------------
    */
    Route::middleware('role:accounting,admin')->group(function () {
        Route::get('/accounting/dashboard', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
        Route::get('/accounting/transactions', [TransactionController::class, 'index'])->name('accounting.transactions.index');
    });

    /*
    |-------------------------------
    | FEE MANAGEMENT (Accounting + Admin)
    |-------------------------------
    */
    Route::middleware('role:admin,accounting')->group(function () {
        Route::resource('fees', FeeController::class);
        Route::post('fees/{fee}/toggle-status', [FeeController::class, 'toggleStatus'])->name('fees.toggleStatus');
        Route::post('fees/assign-to-students', [FeeController::class, 'assignToStudents'])->name('fees.assignToStudents');
    });

    /*
    |-------------------------------
    | SUBJECT MANAGEMENT (Accounting + Admin)
    |-------------------------------
    */
    Route::middleware('role:admin,accounting')->group(function () {
        Route::resource('subjects', SubjectController::class);
        Route::post('subjects/{subject}/enroll-students', [SubjectController::class, 'enrollStudents'])->name('subjects.enrollStudents');
    });

    /*
    |-------------------------------
    | USER MANAGEMENT (Admin Only)
    |-------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class);
    });

    /*
    |-------------------------------
    | NOTIFICATIONS (All Authenticated Users)
    |-------------------------------
    */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    /*
    |-------------------------------
    | NOTIFICATIONS MANAGEMENT (Accounting + Admin)
    |-------------------------------
    */
    Route::middleware('role:accounting,admin')->group(function () {
        Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
    });

});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';