<?php

use App\Http\Controllers\Api\V1\Auth\FirebaseAuthController;
use App\Http\Controllers\Api\V1\AvailableSlotController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\CourseRequestController;
use App\Http\Controllers\Api\V1\GoogleAuthController;
use App\Http\Controllers\Api\V1\InPersonSlotController;
use App\Http\Controllers\Api\V1\LessonSessionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\TeacherLevelController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::apiResource('teachers', TeacherController::class)->only(['store']);
Route::apiResource('students', StudentController::class)->only(['store']);




Route::post('/register', [FirebaseAuthController::class, 'register']);
Route::post('/login', [FirebaseAuthController::class, 'login']);


Route::post('/password/reset', [FirebaseAuthController::class, 'resetPassword']);
Route::post('/google/login', [FirebaseAuthController::class, 'googleLogin']);

Route::apiResource('subjects', SubjectController::class)->only(['index']);
Route::apiResource('skills', SkillController::class)->only(['index']);


// all courses 
Route::get('public-courses', [CourseController::class, 'allCourses']);
Route::get('public-courses/{id}', [CourseController::class, 'courseDetails']);


// all slots
Route::get('/slots', [AvailableSlotController::class, 'index']);
Route::get('/slots/{id}', [AvailableSlotController::class, 'show']);

// all slots
Route::get('/in-person-slots', [InPersonSlotController::class, 'index']);
Route::get('/in-person-slots/{id}', [InPersonSlotController::class, 'show']);


Route::middleware(['auth:sanctum','signed'])->group(function () {
    Route::get('/email/verify/{id}/{hash}', [FirebaseAuthController::class, 'verify'])->name('verification.verify');
});
Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::get('/me', [FirebaseAuthController::class, 'me']);
    Route::post('/me', [FirebaseAuthController::class, 'updateMe']);
    
    Route::post('/logout', [FirebaseAuthController::class, 'logout']);
    Route::delete('/delete', [FirebaseAuthController::class, 'destroy']);


    // Teacher Routes
    Route::apiResource('users', UserController::class);
    Route::apiResource('students', StudentController::class)->except(['store']);

    Route::get('/teacher-levels/progress', [TeacherLevelController::class, 'progressToNextLevel']);
    Route::apiResource('teacher-levels', TeacherLevelController::class);

    Route::apiResource('teachers', TeacherController::class)->except(['store']);
    Route::get('/teacher/{id}/details', [TeacherController::class, 'teacherDetails']);
    Route::post('/teachers/select-main-courses', [TeacherController::class, 'selectMainCourses']);


    Route::apiResource('subjects', SubjectController::class)->except(['index']);
    Route::apiResource('skills', SkillController::class)->except(['index']);
    
    
    Route::apiResource('courses', CourseController::class);
    // Course purchase routes
    Route::post('/courses/payment-intent', [CourseController::class, 'createCoursePaymentIntent']);
    Route::post('/courses/confirm-purchase', [CourseController::class, 'confirmCoursePurchase']);



    
    // available slots
    // Teacher actions
    Route::get('/teacher/slots', [AvailableSlotController::class, 'mySlots']);
    Route::get('/teacher/slots/booked', [AvailableSlotController::class, 'bookedSlots']);
    Route::post('/teacher/slots', [AvailableSlotController::class, 'store']);
    Route::put('/teacher/slots/{availableSlot}', [AvailableSlotController::class, 'update']);
    Route::delete('/teacher/slots/{availableSlot}', [AvailableSlotController::class, 'destroy']);
    // Student actions
    Route::post('/slot/bookings/intent', [AvailableSlotController::class, 'createBookingIntent']);
    Route::post('/slot/bookings/confirm', [AvailableSlotController::class, 'confirmBooking']);
    Route::post('/slot/bookings/{sessionId}/cancel', [AvailableSlotController::class, 'cancelBooking']);
    Route::get('/student/booked-slots', [AvailableSlotController::class, 'studentBookedSlots']);




    // in person slots
    // Teacher actions
    Route::get('/teacher/in-person-slots', [InPersonSlotController::class, 'mySlots']);
    Route::get('/teacher/in-person-slots/booked', [InPersonSlotController::class, 'bookedSlots']);
    Route::post('/teacher/in-person-slots', [InPersonSlotController::class, 'store']);
    Route::put('/teacher/in-person-slots/{inPersonSlot}', [InPersonSlotController::class, 'update']);
    Route::delete('/teacher/in-person-slots/{inPersonSlot}', [InPersonSlotController::class, 'destroy']);
    // Student actions
    Route::post('/in-person-slot/bookings/intent', [InPersonSlotController::class, 'createBookingIntent']);
    Route::post('/in-person-slot/bookings/confirm', [InPersonSlotController::class, 'confirmBooking']);
    Route::get('/student/booked-in-person-slots', [InPersonSlotController::class, 'studentBookedSlots']);




    // Course Request Routes
    Route::get('/my-course-requests', [CourseRequestController::class, 'myRequests']);
    Route::delete('/my-course-requests-delete/{id}', [CourseRequestController::class, 'myRequestsDestroy']);
    Route::post('/course-requests', [CourseRequestController::class, 'store']);
    Route::get('/course-requests', [CourseRequestController::class, 'index']);
    Route::post('/course-request/{id}/approve', [CourseRequestController::class, 'approve']);
    Route::post('/course-request/{id}/reject', [CourseRequestController::class, 'reject']);





    // student enrolled courses
    Route::get('/student/enrolled-courses', [CourseController::class, 'enrolledCourses']);
    Route::get('/student/enrolled-courses/{course}', [CourseController::class, 'enrolledCourseDetails']);
    Route::get('/teacher/enrolled-courses', [CourseController::class, 'teacherEnrolledCourses']);


    // Reviews Routes
    Route::apiResource('reviews', ReviewController::class)->only(['index']);
    Route::post('/reviews/teacher', [ReviewController::class, 'storeTeacherReview']);
    Route::post('/reviews/course', [ReviewController::class, 'storeCourseReview']);


    // Notification Routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/latest', [NotificationController::class, 'latest']);
    Route::post('/notifications/mark-read/{id}', [NotificationController::class, 'markRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);




    // Transaction Routes
    Route::prefix('transactions')->group(function () {
        Route::get('/', [TransactionController::class, 'index']);
        Route::post('/stripe/initiate', [TransactionController::class, 'initiateStripePayment']);
        Route::get('/success', [TransactionController::class, 'success']);
        Route::get('/cancel', [TransactionController::class, 'cancel']);
        Route::post('/stripe/webhook', [TransactionController::class, 'handleWebhook']);
        Route::post('/manual', [TransactionController::class, 'manualStore']);
        Route::get('/{transaction}', [TransactionController::class, 'show']);
        Route::delete('/{transaction}', [TransactionController::class, 'destroy']);
    });

    
});