<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\Management\EvaluationController;
use App\Http\Controllers\Management\PedagogyController;
use App\Http\Controllers\Management\ReportController;
use App\Http\Controllers\Management\SettingsController;
use App\Http\Controllers\Management\UserManagementController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\EnrollmentController;
use App\Http\Controllers\Public\MyEnrollmentsController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\ServiceController;
use App\Http\Controllers\Public\TrainerController;
use App\Http\Controllers\Public\TrainingController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public.home')->name('home');
Route::view('/a-propos', 'public.about')->name('about');
Route::view('/mission', 'public.mission')->name('mission');

Route::get('/formations', [TrainingController::class, 'index'])->name('trainings.index');
Route::get('/formations/{training:slug}', [TrainingController::class, 'show'])->name('trainings.show');
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/tarifs', [ServiceController::class, 'prices'])->name('prices');
Route::get('/formateurs', [TrainerController::class, 'index'])->name('trainers.index');
Route::get('/actualites', [NewsController::class, 'index'])->name('news.index');
Route::get('/actualites/{newsPost:slug}', [NewsController::class, 'show'])->name('news.show');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/inscription', [RegisterController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tableau-de-bord/espaces/{role:slug}', [DashboardController::class, 'show'])
        ->name('dashboard.roles.show');
    Route::post('/deconnexion', LogoutController::class)->name('logout');

    Route::get('/formations/{training:slug}/inscription', [EnrollmentController::class, 'create'])
        ->name('enrollments.create');
    Route::post('/formations/{training:slug}/inscription', [EnrollmentController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('enrollments.store');
    Route::get('/mes-inscriptions', [MyEnrollmentsController::class, 'index'])
        ->name('my-enrollments.index');

    Route::prefix('gestion')->name('management.')->group(function (): void {
        Route::middleware('permission:finance.view')->group(function (): void {
            Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/rapports/export', [ReportController::class, 'export'])->name('reports.export');
        });
        Route::middleware('permission:settings.manage')->group(function (): void {
            Route::get('/parametres', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('/parametres', [SettingsController::class, 'update'])->name('settings.update');
        });
        Route::middleware('permission:users.view')->get('/utilisateurs', [UserManagementController::class, 'index'])
            ->name('users.index');
        Route::middleware('permission:users.manage')->group(function (): void {
            Route::get('/utilisateurs/{user}/roles', [UserManagementController::class, 'editRoles'])->name('users.roles.edit');
            Route::put('/utilisateurs/{user}/roles', [UserManagementController::class, 'updateRoles'])->name('users.roles.update');
        });
        Route::middleware('permission:roles.manage')->group(function (): void {
            Route::get('/roles', [UserManagementController::class, 'roles'])->name('roles.index');
            Route::get('/roles/{role}/permissions', [UserManagementController::class, 'editPermissions'])->name('roles.permissions.edit');
            Route::put('/roles/{role}/permissions', [UserManagementController::class, 'updatePermissions'])->name('roles.permissions.update');
        });

        Route::middleware('permission:pedagogy.manage')->group(function (): void {
            Route::get('/pedagogie', [PedagogyController::class, 'index'])->name('pedagogy.index');
            Route::get('/sessions', [PedagogyController::class, 'sessions'])->name('pedagogy.sessions.index');
            Route::post('/sessions', [PedagogyController::class, 'storeSession'])->name('pedagogy.sessions.store');
            Route::put('/sessions/{session}', [PedagogyController::class, 'updateSession'])->name('pedagogy.sessions.update');
            Route::patch('/sessions/{session}/statut', [PedagogyController::class, 'setSessionStatus'])->name('pedagogy.sessions.status');
            Route::get('/sessions/{session}/presences', [PedagogyController::class, 'attendance'])->name('pedagogy.attendance.show');
            Route::post('/sessions/{session}/presences', [PedagogyController::class, 'saveAttendance'])->name('pedagogy.attendance.store');
            Route::get('/inscriptions', [PedagogyController::class, 'enrollments'])->name('pedagogy.enrollments.index');
            Route::patch('/inscriptions/{enrollment}', [PedagogyController::class, 'reviewEnrollment'])->name('pedagogy.enrollments.update');
            Route::post('/inscriptions/{enrollment}/certificat', [PedagogyController::class, 'issueCertificate'])->name('pedagogy.enrollments.certificate');
            Route::get('/evaluations', [EvaluationController::class, 'index'])->name('pedagogy.evaluations.index');
            Route::get('/evaluations/creer', [EvaluationController::class, 'create'])->name('pedagogy.evaluations.create');
            Route::post('/evaluations', [EvaluationController::class, 'store'])->name('pedagogy.evaluations.store');
            Route::get('/evaluations/{evaluation}', [EvaluationController::class, 'show'])->name('pedagogy.evaluations.show');
            Route::patch('/reponses-evaluation/{response}', [EvaluationController::class, 'gradeResponse'])->name('pedagogy.evaluations.grade');
        });

        Route::middleware('permission:trainings.manage')->group(function (): void {
            Route::get('/formations', [PedagogyController::class, 'trainings'])->name('trainings.index');
            Route::get('/formations/creer', [PedagogyController::class, 'createTraining'])->name('trainings.create');
            Route::post('/formations', [PedagogyController::class, 'storeTraining'])->name('trainings.store');
            Route::get('/formations/{training:slug}', [PedagogyController::class, 'showTraining'])->name('trainings.show');
            Route::get('/formations/{training:slug}/modifier', [PedagogyController::class, 'editTraining'])->name('trainings.edit');
            Route::put('/formations/{training:slug}', [PedagogyController::class, 'updateTraining'])->name('trainings.update');
            Route::post('/formations/{training:slug}/modules', [PedagogyController::class, 'storeModule'])->name('modules.store');
            Route::put('/modules/{module}', [PedagogyController::class, 'updateModule'])->name('modules.update');
            Route::post('/modules/{module}/lecons', [PedagogyController::class, 'storeLesson'])->name('lessons.store');
            Route::put('/lecons/{lesson}', [PedagogyController::class, 'updateLesson'])->name('lessons.update');
        });
    });

    Route::middleware('permission:learning.access')->group(function (): void {
        Route::get('/mes-cours', [LearningController::class, 'index'])->name('learning.index');
        Route::get('/mes-cours/lecons/{lesson}', [LearningController::class, 'showLesson'])->name('learning.lessons.show');
        Route::post('/mes-cours/lecons/{lesson}/terminer', [LearningController::class, 'completeLesson'])->name('learning.lessons.complete');
        Route::get('/mes-evaluations/{evaluation}', [LearningController::class, 'showEvaluation'])->name('learning.evaluations.show');
        Route::post('/mes-evaluations/{evaluation}', [LearningController::class, 'submitEvaluation'])->name('learning.evaluations.submit');
        Route::get('/mes-certificats', [LearningController::class, 'certificates'])->name('learning.certificates.index');
    });
});
