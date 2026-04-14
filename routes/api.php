<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AnswerKeyController;
use App\Http\Controllers\Api\AnswerSheetController;
use App\Http\Controllers\Api\DashboardStatsController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\Admin\CollegeController;
use App\Http\Controllers\Api\Admin\EmployeeController;
use App\Http\Controllers\Api\Admin\ExamScheduleController;
use App\Http\Controllers\Api\Admin\OfficeController;
use App\Http\Controllers\Api\Admin\ProgramController;
use App\Http\Controllers\Api\Admin\ProgramRequirementController;
use App\Http\Controllers\Api\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Api\Admin\SubjectController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\CollegeDean\CollegeDeanManagementController;
use App\Http\Controllers\Api\EntranceExaminer\ExamSubjectController;
use App\Http\Controllers\Api\EntranceExaminer\OmrScanController;
use App\Http\Controllers\Api\Instructor\InstructorManagementController;
use App\Http\Controllers\Api\Instructor\TermOmrScanController;
use App\Http\Controllers\Api\Student\StudentRecommendationController;
use App\Http\Controllers\Api\Student\StudentQrController;
use App\Http\Controllers\Api\Student\StudentScheduleController;
use App\Http\Controllers\Api\Student\StudentSubjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password/send-code', [AuthController::class, 'sendForgotPasswordCode']);
Route::post('/forgot-password/reset', [AuthController::class, 'resetPasswordWithCode']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email-verification/send', [AuthController::class, 'sendEmailVerificationCode']);
    Route::post('/email-verification/verify', [AuthController::class, 'verifyEmailCode']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/email', [AuthController::class, 'updateEmail']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);
    Route::get('/notifications/summary', [NotificationController::class, 'summary']);

    // General Resources
    Route::apiResource('exams', ExamController::class);
    Route::get('programs', [ProgramController::class, 'index']);
    Route::get('subjects', [SubjectController::class, 'index']);
    Route::apiResource('answer-sheets', AnswerSheetController::class);
    Route::post('/answer-sheets/generate', [AnswerSheetController::class, 'generatePdf']);
    Route::post('/answer-sheets/generate-term', [AnswerSheetController::class, 'generateTermPdf']);
    Route::post('/answer-sheets/print-selected', [AnswerSheetController::class, 'printSelected']);
    Route::get('/answer-sheets/{id}/print', [AnswerSheetController::class, 'printSingle']);
    // CRUD Routes
    Route::apiResource('answer-keys', AnswerKeyController::class);

    // PDF Download Route
    Route::get('/answer-keys/{id}/download', [AnswerKeyController::class, 'downloadPdf']);

    // Admin Group: Controllers are only resolved when these routes are hit
    Route::prefix('admin')->group(function () {
        Route::get('reports/all-users', [ReportController::class, 'index']);
        Route::get('activities', [ReportController::class, 'adminActivities']);
        Route::get('students', [ReportController::class, 'adminStudents']);
        Route::get('scheduled-students', [ReportController::class, 'adminScheduledStudents']);
        Route::get('scheduled-students/download', [ReportController::class, 'adminScheduledStudentsDownload']);
        Route::get('exam-reports', [ReportController::class, 'adminExamReports']);
        Route::get('exam-reports/{exam}', [ReportController::class, 'adminExamReportDetail']);
        Route::get('users', [UserManagementController::class, 'index']);
        Route::get('dashboard/stats', [DashboardStatsController::class, 'admin']);

        Route::apiResource('subjects', SubjectController::class);
        Route::apiResource('offices', OfficeController::class);
        Route::apiResource('colleges', CollegeController::class);
        Route::apiResource('exam-schedules', ExamScheduleController::class);
        Route::apiResource('programs', ProgramController::class);
        Route::apiResource('program-requirements', ProgramRequirementController::class);
        Route::apiResource('employees', EmployeeController::class);
        Route::post('student-accounts', [AdminStudentController::class, 'store']);
        Route::put('student-accounts/{student}', [AdminStudentController::class, 'update']);
        Route::delete('student-accounts/{student}', [AdminStudentController::class, 'destroy']);
    });

    // Department Head Group
    Route::prefix('college_dean')->group(function () {
        Route::get('dashboard/stats', [DashboardStatsController::class, 'collegeDean']);
        Route::get('activities', [ReportController::class, 'collegeDeanActivities']);
        Route::get('students', [CollegeDeanManagementController::class, 'students']);
        Route::get('subjects', [CollegeDeanManagementController::class, 'subjects']);
        Route::get('instructors', [CollegeDeanManagementController::class, 'instructors']);
        Route::get('screening-schedules', [CollegeDeanManagementController::class, 'screeningSchedules']);
        Route::get('screening-schedules/eligible-students', [CollegeDeanManagementController::class, 'screeningEligibleStudents']);
        Route::post('screening-schedules', [CollegeDeanManagementController::class, 'storeScreeningSchedule']);
        Route::put('screening-schedules/{scheduleId}', [CollegeDeanManagementController::class, 'updateScreeningSchedule']);
        Route::delete('screening-schedules/{scheduleId}', [CollegeDeanManagementController::class, 'destroyScreeningSchedule']);
        Route::post('screening-schedules/assign-students', [CollegeDeanManagementController::class, 'assignScreeningStudents']);
        Route::delete('screening-schedules/assignments/{examId}/{scheduleId}/{userId}', [CollegeDeanManagementController::class, 'unassignScreeningStudent']);

        Route::get('subject-assignments/students', [CollegeDeanManagementController::class, 'studentAssignments']);
        Route::post('subject-assignments/students', [CollegeDeanManagementController::class, 'storeStudentAssignment']);
        Route::delete('subject-assignments/students/{id}', [CollegeDeanManagementController::class, 'destroyStudentAssignment']);

        Route::get('subject-assignments/instructors', [CollegeDeanManagementController::class, 'instructorAssignments']);
        Route::post('subject-assignments/instructors', [CollegeDeanManagementController::class, 'storeInstructorAssignment']);
        Route::delete('subject-assignments/instructors/{id}', [CollegeDeanManagementController::class, 'destroyInstructorAssignment']);
    });

    // Entrance Group
    Route::prefix('entrance')->group(function () {
        Route::get('dashboard/stats', [DashboardStatsController::class, 'entrance']);
        Route::get('schedules', [AnswerSheetController::class, 'entranceSchedules']);
        Route::get('scheduled-students', [AnswerSheetController::class, 'entranceScheduledStudents']);
        Route::get('reports/examinee-results', [ReportController::class, 'entranceExamineeResults']);
        Route::get('reports/examinee-results/{answerSheetId}', [ReportController::class, 'entranceExamineeResultDetail']);
        Route::get('students/took-exams', [ReportController::class, 'entranceStudentsWhoTookExams']);
        Route::post('omr/check', [OmrScanController::class, 'check']);
        Route::apiResource('exam-subjects', ExamSubjectController::class);
        // Define routes here
    });

    // Instructor Group
    Route::prefix('instructor')->group(function () {
        Route::get('dashboard/stats', [DashboardStatsController::class, 'instructor']);
        Route::get('students', [InstructorManagementController::class, 'students']);
        Route::get('subjects', [InstructorManagementController::class, 'subjects']);
        Route::get('subjects/{subjectId}/students', [InstructorManagementController::class, 'subjectStudents']);
        Route::post('omr/check-term', [TermOmrScanController::class, 'check']);
    });

    // Student Group
    Route::prefix('student')->group(function () {
        Route::get('dashboard/stats', [DashboardStatsController::class, 'student']);
        Route::post('answer-sheets/scan', [AnswerSheetController::class, 'scanAndLink']);
        Route::get('qr', [StudentQrController::class, 'show']);
        Route::get('subjects', [StudentSubjectController::class, 'index']);
        Route::get('schedules', [StudentScheduleController::class, 'index']);
        Route::get('reports', [ReportController::class, 'studentExamResults']);
        Route::get('program-recommendations', [StudentRecommendationController::class, 'index']);
        Route::post('program-recommendations/select', [StudentRecommendationController::class, 'saveSelection']);
        Route::post('program-recommendations/decision', [StudentRecommendationController::class, 'saveScreeningDecision']);
    });
});
