<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeacherController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/forbidden', [AuthController::class, 'forbidden'])->name('forbidden');

Route::middleware('auth:sanctum')->group(function () {

    route::prefix('/member')->group(function () {
        Route::post('/add', [MemberController::class, 'addMember']);
        Route::post('/edit', [MemberController::class, 'editMember']);
        Route::post('/get', [MemberController::class, 'getMember']);
        Route::post('/delete', [MemberController::class, 'deleteMember']);
    });
    route::prefix('/cash')->group(function () {
        Route::post('/getClassCashSummary', [CashController::class, 'getClassCashSummary']);
        Route::post('/listPembayaranPerBulan', [CashController::class, 'listPembayaranPerBulan']);
        Route::post('/add', [CashController::class, 'addTransaction']);
        Route::post('/edit', [CashController::class, 'editTransaction']);
        Route::post('/getCashLog', [CashController::class, 'getCashLog']);
        // Route::post('/delete', [CashController::class, 'deleteMember']);
    });
    route::prefix('/teacher')->group(function () {
        Route::post('/add', [TeacherController::class, 'addTeacher']);
        Route::post('/edit', [TeacherController::class, 'editTeacher']);
        Route::post('/get', [TeacherController::class, 'getTeacher']);
        Route::post('/delete', [TeacherController::class, 'deleteTeacher']);
    });
    route::prefix('/subject')->group(function () {
        Route::post('/add', [SubjectController::class, 'addSubject']);
        Route::post('/edit', [SubjectController::class, 'editSubject']);
        Route::post('/get', [SubjectController::class, 'getSubject']);
        Route::post('/delete', [SubjectController::class, 'deleteSubject']);
    });
    route::prefix('/task')->group(function () {
        Route::post('/updateStatus', [TaskController::class, 'updateStatus']);
        Route::post('/add', [TaskController::class, 'addTask']);
        Route::post('/edit', [TaskController::class, 'editTask']);
        Route::post('/get', [TaskController::class, 'getTask']);
        Route::post('/delete', [TaskController::class, 'deleteTask']);
    });
    route::prefix('/schedule')->group(function () {
        Route::post('/GetClassSubjectSchedule', [ScheduleController::class, 'GetClassSubjectSchedule']);
        Route::post('/SetSchedule', [ScheduleController::class, 'SetSchedule']);
        Route::post('/RemoveSchedule', [ScheduleController::class, 'RemoveSchedule']);
        Route::post('/GetIdleSubject', [ScheduleController::class, 'GetIdleSubject']);
    });

    Route::post('/getCountData', [DashboardController::class, 'getCountData']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
