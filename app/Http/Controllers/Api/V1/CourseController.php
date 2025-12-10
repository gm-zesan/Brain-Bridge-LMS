<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\UploadVideoToFirebase;
use App\Mail\CourseEnrolledMail;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\VideoLesson;
use App\Services\FirebaseService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Courses",
 *     description="API Endpoints for managing courses"
 * )
 */
class CourseController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/public-courses",
     *     summary="Get all published courses",
     *     description="Retrieve all published courses available for public access. Includes course details, teacher information, modules, and video lessons.",
     *     operationId="getAllPublicCourses",
     *     tags={"Courses"},
     *     @OA\Response(
     *         response=200,
     *         description="List of published courses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Complete Web Development Bootcamp"),
     *                     @OA\Property(property="description", type="string", example="Learn web development from scratch with HTML, CSS, JavaScript, and more"),
     *                     @OA\Property(property="subject_id", type="integer", example=5),
     *                     @OA\Property(property="teacher_id", type="integer", example=10),
     *                     @OA\Property(property="price", type="number", format="float", example=99.99),
     *                     @OA\Property(property="duration_weeks", type="integer", example=12, nullable=true),
     *                     @OA\Property(property="difficulty_level", type="string", example="beginner", enum={"beginner", "intermediate", "advanced"}),
     *                     @OA\Property(property="thumbnail_url", type="string", example="https://example.com/thumbnails/course1.jpg", nullable=true),
     *                     @OA\Property(property="is_published", type="boolean", example=true),
     *                     @OA\Property(property="enrollment_count", type="integer", example=150),
     *                    @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                    @OA\Property(property="reviews_count", type="integer", example=45),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-01T10:30:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-11-16T12:45:00.000000Z"),
     *                     @OA\Property(
     *                         property="subject",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="name", type="string", example="Web Development"),
     *                         @OA\Property(property="description", type="string", example="Learn modern web development technologies", nullable=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00.000000Z")
     *                     ),
     *                     @OA\Property(
     *                         property="teacher",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="role", type="string", example="teacher"),
     *                         @OA\Property(property="profile_picture", type="string", example="https://example.com/profiles/john.jpg", nullable=true),
     *                         @OA\Property(property="bio", type="string", example="Experienced web developer with 10+ years", nullable=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-15T08:30:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2024-11-10T14:20:00.000000Z")
     *                     ),
     *                     @OA\Property(
     *                         property="modules",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="course_id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="Introduction to HTML"),
     *                             @OA\Property(property="description", type="string", example="Learn the basics of HTML structure and tags", nullable=true),
     *                             @OA\Property(property="order", type="integer", example=1),
     *                             @OA\Property(property="duration_minutes", type="integer", example=120, nullable=true),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-01T11:00:00.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2024-11-01T11:00:00.000000Z"),
     *                             @OA\Property(
     *                                 property="video_lessons",
     *                                 type="array",
     *                                 @OA\Items(
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=1),
     *                                     @OA\Property(property="module_id", type="integer", example=1),
     *                                     @OA\Property(property="title", type="string", example="HTML Basics - Part 1"),
     *                                     @OA\Property(property="description", type="string", example="Introduction to HTML tags and structure", nullable=true),
     *                                     @OA\Property(property="video_url", type="string", example="https://example.com/videos/lesson1.mp4"),
     *                                     @OA\Property(property="duration_seconds", type="integer", example=1800),
     *                                     @OA\Property(property="order", type="integer", example=1),
     *                                     @OA\Property(property="is_preview", type="boolean", example=true),
     *                                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-01T11:30:00.000000Z"),
     *                                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-11-01T11:30:00.000000Z")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve courses")
     *         )
     *     )
     * )
    */

    public function allCourses()
    {
        $courses = Course::with(['subject', 'teacher', 'modules', 'modules.videoLessons'])
            ->where('is_published', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $courses,
        ], 200);
    }

    
    /**
     * @OA\Get(
     *     path="/api/public-courses/{id}",
     *     tags={"Courses"},
     *     summary="Get a specific course by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Course ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Course retrieved successfully"),
     *     @OA\Response(response=404, description="Course not found")
     * )
    */

    public function courseDetails($id)
    {
        $course = Course::with('subject', 'teacher', 'modules', 'modules.videoLessons','reviews.reviewer:id,name')->find($id);
        $course->modules->transform(function ($module) {
            $module->videoLessons->transform(function ($video, $index) {
                $video->is_accessible = $index === 0;
                if ($index !== 0) {
                    unset($video->video_url);
                }
                return $video;
            });
            return $module;
        });

        return response()->json($course, 200);
    }



    /**
     * @OA\Post(
     *     path="/api/courses/payment-intent",
     *     tags={"Courses"},
     *     summary="Create payment intent for course purchase",
     *     description="Creates a Stripe payment intent for purchasing a course. Returns payment details or indicates if the course is free.",
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"course_id"},
     *             @OA\Property(property="course_id", type="integer", example=1, description="ID of the course to purchase")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment intent created successfully or free course",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="requires_payment", type="boolean", example=true),
     *             @OA\Property(property="client_secret", type="string", example="pi_xxxxx_secret_xxxxx"),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_xxxxx"),
     *             @OA\Property(property="amount", type="number", format="float", example=99.99),
     *             @OA\Property(
     *                 property="course",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Complete Web Development Course"),
     *                 @OA\Property(property="subject", type="string", example="Programming"),
     *                 @OA\Property(property="teacher", type="string", example="John Doe"),
     *                 @OA\Property(property="price", type="number", format="float", example=99.99),
     *                 @OA\Property(property="old_price", type="number", format="float", example=149.99, nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Course not available or already purchased",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="You already own this course")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Course not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Course not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create payment intent"),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
    */

    public function createCoursePaymentIntent(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'points_to_use' => 'nullable',
            'new_payment_amount' => 'nullable',
        ]);

        try {
            $course = Course::with('subject', 'teacher')
                ->where('is_published', true)
                ->findOrFail($validated['course_id']);

            // Check if user already purchased this course
            if (CourseEnrollment::where('course_id', $course->id)
                    ->where('student_id', Auth::id())
                    ->exists()) {
                return response()->json(['message' => 'You already own this course'], 400);
            }

            // Check if price is zero (free course)
            if ($course->price <= 0) {
                return response()->json([
                    'success' => true,
                    'requires_payment' => false,
                    'message' => 'This is a free course. Proceed to confirm enrollment.',
                    'course' => [
                        'id' => $course->id,
                        'title' => $course->title,
                        'teacher' => $course->teacher->name,
                        'subject' => $course->subject->name ?? 'Course',
                    ]
                ]);
            }

            $amountPaid = $course->price;

            if (isset($validated['points_to_use']) && isset($validated['new_payment_amount'])) {
                // Deduct points from user
                $user = Auth::user();
                $pointsToUse = (int)$validated['points_to_use'];
                $user->points -= $pointsToUse;
                $user->save();

                // Adjust amount paid
                $amountPaid = (float)$validated['new_payment_amount'];
            }

            $amountPaid = $amountPaid + 7.95; // add fixed fee

            // Create payment intent
            $paymentIntent = $this->paymentService->createPaymentIntent(
                $amountPaid,
                'usd',
                [
                    'course_id' => $course->id,
                    'student_id' => Auth::id(),
                    'teacher_id' => $course->teacher_id,
                    'course_title' => $course->title,
                ]
            );

            if (!$paymentIntent['success']) {
                return response()->json([
                    'message' => 'Failed to create payment intent',
                    'error' => $paymentIntent['error']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'requires_payment' => true,
                'client_secret' => $paymentIntent['client_secret'],
                'payment_intent_id' => $paymentIntent['payment_intent_id'],
                'amount' => $paymentIntent['amount'],
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'teacher' => $course->teacher->name,
                    'subject' => $course->subject->name ?? 'Course',
                    'price' => $amountPaid,
                    'old_price' => $course->old_price,
                ]
            ]);

        } catch (\Exception $e) {
            
            return response()->json([
                'message' => 'Failed to create payment intent'
            ], 500);
        }
    }



    /**
     * @OA\Post(
     *     path="/api/courses/confirm-purchase",
     *     tags={"Courses"},
     *     summary="Confirm course purchase",
     *     description="Confirms the course purchase after payment verification. Creates enrollment record and sends confirmation emails to both student and teacher.",
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"course_id"},
     *             @OA\Property(property="course_id", type="integer", example=1, description="ID of the course to purchase"),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_xxxxx", description="Stripe payment intent ID (required for paid courses)", nullable=true),
     *            @OA\Property(property="points_to_use", type="integer", example=100, description="Number of points to use for discount", nullable=true),
     *            @OA\Property(property="new_payment_amount", type="number", format="float", example=79.99, description="New payment amount after applying points", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Course purchase confirmed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Course purchased successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="enrollment_id", type="integer", example=123),
     *                 @OA\Property(property="course_id", type="integer", example=1),
     *                 @OA\Property(property="payment_status", type="string", example="paid", enum={"free", "paid"}),
     *                 @OA\Property(property="amount_paid", type="number", format="float", example=99.99)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - Validation or business logic error",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="This course is not available")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="You already own this course")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Payment intent ID is required")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Failed to verify payment")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="message", type="string", example="Payment not completed"),
     *                     @OA\Property(property="payment_status", type="string", example="processing")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Course not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Course not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error - Transaction rolled back",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Purchase confirmation failed. Please contact support.")
     *         )
     *     )
     * )
    */

    public function confirmCoursePurchase(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'payment_intent_id' => 'nullable|string', // nullable for free courses
            'points_to_use' => 'nullable',
            'new_payment_amount' => 'nullable',
        ]);

        DB::beginTransaction();
        $course = Course::with('teacher', 'subject')
            ->where('is_published', true)
            ->with('teacher', 'subject')
            ->lockForUpdate()
            ->findOrFail($validated['course_id']);

        // Re-check duplicate purchase
        if (CourseEnrollment::where('course_id', $course->id)
                ->where('student_id', Auth::id())
                ->exists()) {
            DB::rollBack();
            return response()->json(['message' => 'You already own this course'], 400);
        }

        // Verify payment if required
        $paymentStatus = 'free';
        $paymentIntentId = null;
        $amountPaid = 0;

        if ($course->price > 0) {
            if (!$validated['payment_intent_id']) {
                DB::rollBack();
                return response()->json(['message' => 'Payment intent ID is required'], 400);
            }

            $paymentStatus = 'paid';
            $paymentIntentId = $validated['payment_intent_id'];
            $amountPaid = $course->price;
        }

        $amountPaid = $amountPaid + 7.95; // add fixed fee

        // Create course enrollment
        $enrollment = CourseEnrollment::create([
            'course_id' => $course->id,
            'student_id' => Auth::id(),
            'teacher_id' => $course->teacher_id,
            'enrolled_at' => now()->toDateTimeString(),
            'payment_status' => $paymentStatus,
            'payment_intent_id' => $paymentIntentId,
            'payment_method' => $paymentIntentId ? 'stripe' : null,
            'amount_paid' => (float)$amountPaid,
            'currency' => 'usd',
            'paid_at' => $paymentStatus === 'paid' ? now()->toDateTimeString() : null,
            'status' => 'active',
        ]);

        // Increment enrollment count
        $course->increment('enrollment_count');

        DB::commit();

        // Send emails AFTER commit
        // Mail::to($course->teacher->email)->queue(new CourseEnrolledMail($enrollment, 'teacher'));
        // Mail::to(Auth::user()->email)->queue(new CourseEnrolledMail($enrollment, 'student'));

        return response()->json([
            'success' => true,
            'message' => 'Course purchased successfully',
            'data' => [
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
                'enrolled_at' => $enrollment->enrolled_at,
            ]
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/student/enrolled-courses",
     *     tags={"Courses"},
     *     summary="Get all courses the authenticated student is enrolled in",
     *     @OA\Response(response=200, description="List of enrolled courses retrieved successfully")
     * )
    */
    public function enrolledCourses()
    {
        $enrollments = CourseEnrollment::with(['course.subject', 'course.teacher'])
            ->where('student_id', Auth::id())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $enrollments,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/student/enrolled-courses/{course_id}",
     *     tags={"Courses"},
     *     summary="Get details of a specific enrolled course for the authenticated student",
     *     @OA\Parameter(
     *         name="course_id",
     *         in="path",
     *         required=true,
     *         description="Course ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Enrolled course details retrieved successfully"),
     *     @OA\Response(response=404, description="Enrollment not found")
     * )
    */
    public function enrolledCourseDetails(Course $course)
    {
        $enrollment = CourseEnrollment::with(['course.subject', 'course.teacher', 'course.modules', 'course.modules.videoLessons'])
            ->where('student_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Enrollment not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ], 200);
    }


    /**
     * @OA\Get(
     *    path="/api/teacher/enrolled-courses",
     *     tags={"Courses"},
     *     summary="Get all students enrolled in the authenticated teacher's courses",
     *     @OA\Response(response=200, description="List of enrolled students retrieved successfully")
     * )
    */
    
    public function teacherEnrolledCourses()
    {
        $enrollments = CourseEnrollment::with(['course.subject', 'student'])
            ->whereHas('course', function ($query) {
                $query->where('teacher_id', Auth::id());
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $enrollments,
        ], 200);
    }



    /**
     * @OA\Get(
     *     path="/api/courses",
     *     tags={"Courses"},
     *     summary="Get all published courses",
     *     @OA\Response(response=200, description="List of courses retrieved successfully")
     * )
    */

    public function index()
    {
        $query = Course::with(['subject', 'teacher', 'modules', 'modules.videoLessons']);

        if(!Auth::user()->hasRole('admin')) {
            $query->where('teacher_id', Auth::id());
        }

        $courses = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $courses,
        ], 200);
    }

    


    /**
     * @OA\Post(
     *     path="/api/courses",
     *     summary="Create a new course with modules and videos",
     *     description="Creates a complete course structure including multiple modules and video lessons. Each module can contain multiple videos. Supports file uploads for course thumbnail and video files.",
     *     operationId="createCourse",
     *     tags={"Courses"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Course creation data with modules and videos",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "description", "subject_id", "price", "modules"},
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     maxLength=255,
     *                     description="Course title",
     *                     example="Complete Web Development Bootcamp 2024"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     description="Detailed course description",
     *                     example="Master modern web development from scratch. Learn HTML5, CSS3, JavaScript, React, Node.js, and Laravel with hands-on projects."
     *                 ),
     *                 @OA\Property(
     *                     property="thumbnail_url",
     *                     type="string",
     *                     format="binary",
     *                     description="Course thumbnail image (JPEG, PNG, GIF - Max: 5MB)"
     *                 ),
     *                 @OA\Property(
     *                     property="subject_id",
     *                     type="integer",
     *                     description="Subject ID from subjects table",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="price",
     *                     type="number",
     *                     format="float",
     *                     description="Current course price",
     *                     example=2999.99
     *                 ),
     *                 @OA\Property(
     *                     property="old_price",
     *                     type="number",
     *                     format="float",
     *                     description="Original price before discount (optional)",
     *                     example=4999.99
     *                 ),
     *                 @OA\Property(
     *                     property="is_published",
     *                     type="boolean",
     *                     description="Publish status (default: false)",
     *                     example=true
     *                 ),
     *                 @OA\Property(
     *                     property="modules",
     *                     type="array",
     *                     description="Array of course modules (minimum 1 required)",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"title", "order_index"},
     *                         @OA\Property(
     *                             property="title",
     *                             type="string",
     *                             maxLength=255,
     *                             description="Module title",
     *                             example="Introduction to Web Development"
     *                         ),
     *                         @OA\Property(
     *                             property="description",
     *                             type="string",
     *                             description="Module description (optional)",
     *                             example="Learn the fundamentals of web development and setup your development environment"
     *                         ),
     *                         @OA\Property(
     *                             property="order_index",
     *                             type="integer",
     *                             minimum=1,
     *                             description="Display order of module",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="videos",
     *                             type="array",
     *                             description="Array of video lessons for this module (optional)",
     *                             @OA\Items(
     *                                 type="object",
     *                                 required={"title", "file"},
     *                                 @OA\Property(
     *                                     property="title",
     *                                     type="string",
     *                                     maxLength=255,
     *                                     description="Video lesson title",
     *                                     example="What is HTML and How Does it Work?"
     *                                 ),
     *                                 @OA\Property(
     *                                     property="description",
     *                                     type="string",
     *                                     description="Video lesson description (optional)",
     *                                     example="Learn about HTML structure, tags, and elements with practical examples"
     *                                 ),
     *                                 @OA\Property(
     *                                     property="duration_hours",
     *                                     type="number",
     *                                     format="float",
     *                                     minimum=0,
     *                                     description="Video duration in hours (optional)",
     *                                     example=1.5
     *                                 ),
     *                                 @OA\Property(
     *                                     property="file",
     *                                     type="string",
     *                                     format="binary",
     *                                     description="Video file (MP4, AVI, MOV - Max: 500MB)"
     *                                 ),
     *                                 @OA\Property(
     *                                     property="is_published",
     *                                     type="boolean",
     *                                     description="Video publish status (default: false)",
     *                                     example=true
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 example={
     *                     "title": "Complete Web Development Bootcamp 2024",
     *                     "description": "Master modern web development",
     *                     "subject_id": 1,
     *                     "price": 2999.99,
     *                     "old_price": 4999.99,
     *                     "is_published": true,
     *                     "modules": {
     *                         {
     *                             "title": "HTML Fundamentals",
     *                             "description": "Learn HTML from basics to advanced",
     *                             "order_index": 1,
     *                             "videos": {
     *                                 {
     *                                     "title": "Introduction to HTML",
     *                                     "description": "What is HTML?",
     *                                     "duration_hours": 0.5,
     *                                     "is_published": true
     *                                 },
     *                                 {
     *                                     "title": "HTML Tags and Elements",
     *                                     "duration_hours": 1.0,
     *                                     "is_published": true
     *                                 }
     *                             }
     *                         },
     *                         {
     *                             "title": "CSS Styling",
     *                             "order_index": 2,
     *                             "videos": {
     *                                 {
     *                                     "title": "CSS Basics",
     *                                     "duration_hours": 0.75,
     *                                     "is_published": true
     *                                 }
     *                             }
     *                         }
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Course created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Course created successfully with 2 modules and 5 videos"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="course",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Complete Web Development Bootcamp 2024"),
     *                     @OA\Property(property="slug", type="string", example="complete-web-development-bootcamp-2024"),
     *                     @OA\Property(property="description", type="string", example="Master modern web development"),
     *                     @OA\Property(property="thumbnail_url", type="string", example="thumbnails/1699123456_course-thumb.jpg"),
     *                     @OA\Property(property="subject_id", type="integer", example=1),
     *                     @OA\Property(property="teacher_id", type="integer", example=5),
     *                     @OA\Property(property="price", type="number", format="float", example=2999.99),
     *                     @OA\Property(property="old_price", type="number", format="float", example=4999.99),
     *                     @OA\Property(property="is_published", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-11-07T10:30:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-11-07T10:30:00.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="modules",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="course_id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="HTML Fundamentals"),
     *                         @OA\Property(property="description", type="string", example="Learn HTML from basics to advanced"),
     *                         @OA\Property(property="order_index", type="integer", example=1),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="videos",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="module_id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Introduction to HTML"),
     *                         @OA\Property(property="description", type="string", example="What is HTML?"),
     *                         @OA\Property(property="duration_hours", type="number", format="float", example=0.5),
     *                         @OA\Property(property="video_url", type="string", example="videos/1699123456_intro-html.mp4"),
     *                         @OA\Property(property="video_path", type="string", example="public/videos/1699123456_intro-html.mp4"),
     *                         @OA\Property(property="filename", type="string", example="1699123456_intro-html.mp4"),
     *                         @OA\Property(property="is_published", type="boolean", example=true),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="statistics",
     *                     type="object",
     *                     @OA\Property(property="total_modules", type="integer", example=2),
     *                     @OA\Property(property="total_videos", type="integer", example=5),
     *                     @OA\Property(property="total_duration_hours", type="number", format="float", example=7.5)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="title",
     *                     type="array",
     *                     @OA\Items(type="string", example="The title field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="modules.0.videos.0.file",
     *                     type="array",
     *                     @OA\Items(type="string", example="The video file must be a file of type: mp4, avi, mov.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated. Please login to continue.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User not authorized as teacher",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only teachers can create courses")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error - Course creation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create course. Please try again."),
     *             @OA\Property(property="error", type="string", example="SQLSTATE[23000]: Integrity constraint violation")
     *         )
     *     )
     * )
    */
    

    public function store(Request $request)
    {
        // Validate incoming request
        $validated = $request->validate([
            // Course validation
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'thumbnail_url' => 'nullable|file|image|mimes:jpeg,jpg,png,gif|max:5120', // 5MB
            'subject_id' => 'required|exists:subjects,id',
            'price' => 'required|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'is_published' => 'nullable|boolean',

            // Modules validation
            'modules' => 'required|array|min:1',
            'modules.*.title' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.order_index' => 'required|integer|min:1',

            // Videos validation
            'modules.*.videos' => 'nullable|array',
            'modules.*.videos.*.title' => 'required|string|max:255',
            'modules.*.videos.*.description' => 'nullable|string',
            'modules.*.videos.*.duration_hours' => 'nullable|numeric|min:0',
            'modules.*.videos.*.file' => 'required|file|mimetypes:video/mp4,video/avi,video/mov,video/quicktime|max:102400', // 100MB
            'modules.*.videos.*.is_published' => 'nullable|boolean',
        ]);

        DB::beginTransaction();

        try {
            // Prepare course data
            $courseData = [
                'title' => $validated['title'],
                'description' => $validated['description'],
                'subject_id' => $validated['subject_id'],
                'price' => $validated['price'],
                'old_price' => $validated['old_price'] ?? null,
                'is_published' => $request->is_published ?? false,
                'teacher_id' => Auth::id(),
            ];

            // Handle thumbnail upload
            if ($request->hasFile('thumbnail_url')) {
                $thumbnail = $request->file('thumbnail_url');
                $thumbnailName = time() . '_' . uniqid() . '.' . $thumbnail->getClientOriginalExtension();
                $thumbnailPath = $thumbnail->storeAs('thumbnails', $thumbnailName, 'public');
                $courseData['thumbnail_url'] = $thumbnailPath;
            }

            // Create course
            $course = Course::create($courseData);

            $createdModules = [];
            $createdVideos = [];

            // Process modules and videos
            foreach ($validated['modules'] as $moduleData) {
                // Create module
                $module = Module::create([
                    'course_id' => $course->id,
                    'title' => $moduleData['title'],
                    'description' => $moduleData['description'] ?? null,
                    'order_index' => $moduleData['order_index'],
                ]);

                $createdModules[] = $module;

                // Process videos for this module
                if (!empty($moduleData['videos'])) {
                    foreach ($moduleData['videos'] as $videoData) {
                        $videoFile = $videoData['file'];

                        $videoName = time() . '_' . uniqid() . '.' . $videoFile->getClientOriginalExtension();
                        $videoPath = $videoFile->storeAs('videos', $videoName, 'public');

                        // Create video lesson with pending status
                        $videoLesson = VideoLesson::create([
                            'module_id' => $module->id,
                            'title' => $videoData['title'],
                            'description' => $videoData['description'] ?? null,
                            'duration_hours' => $videoData['duration_hours'] ?? 0,
                            'video_path' => $videoPath, // Will be set after upload
                            'filename' => $videoName,
                            'file_size' => $videoFile->getSize(),
                            'mime_type' => $videoFile->getMimeType(),
                            'is_published' => true, // Don't publish until uploaded
                        ]);

                        $createdVideos[] = $videoLesson;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Course created successfully.',
                'data' => [
                    'course' => $course,
                    'modules' => $createdModules,
                    'videos' => $createdVideos
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Course creation failed: ' . $e->getMessage()
            ], 500);
        }
    }



    /**
     * @OA\Get(
     *     path="/api/courses/{id}",
     *     tags={"Courses"},
     *     summary="Get a specific course by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Course ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Course retrieved successfully"),
     *     @OA\Response(response=404, description="Course not found")
     * )
    */

    public function show(Course $course)
    {
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        $course->load('subject', 'teacher', 'modules', 'modules.videoLessons');

        return response()->json($course, 200);
    }

    /**
     * @OA\Put(
     *     path="/api/courses/{courseId}",
     *     summary="Update a course with modules and videos",
     *     description="Update course details, modules, and video lessons. Supports creating, updating, and deleting nested resources.",
     *     operationId="updateCourse",
     *     tags={"Courses"},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Course ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Course update data with modules and videos",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", maxLength=255, example="Complete Adobe Illustrator Course 2024"),
     *                 @OA\Property(property="description", type="string", example="Master Adobe Illustrator from beginner to advanced"),
     *                 @OA\Property(property="subject_id", type="integer", example=1),
     *                 @OA\Property(property="price", type="number", format="float", example=149.99),
     *                 @OA\Property(property="old_price", type="number", format="float", nullable=true, example=299.99),
     *                 @OA\Property(property="is_published", type="boolean", example=true),
     *                 
     *                 @OA\Property(
     *                     property="modules",
     *                     type="array",
     *                     description="Array of modules. Include 'id' to update, omit to create new. Modules not in array will be deleted.",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", nullable=true, example=4, description="Module ID for update, null for create"),
     *                         @OA\Property(property="title", type="string", maxLength=255, example="Introduction to Illustrator"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Learn the basics"),
     *                         @OA\Property(property="order_index", type="integer", minimum=1, example=1),
     *                         
     *                         @OA\Property(
     *                             property="videos",
     *                             type="array",
     *                             description="Array of video lessons. Include 'id' to update, omit to create new. Videos not in array will be deleted.",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", nullable=true, example=5, description="Video ID for update, null for create"),
     *                                 @OA\Property(property="title", type="string", maxLength=255, example="Getting Started"),
     *                                 @OA\Property(property="description", type="string", nullable=true, example="Introduction to the interface"),
     *                                 @OA\Property(property="duration_hours", type="number", format="float", nullable=true, example=2.5),
     *                                 @OA\Property(property="is_published", type="boolean", nullable=true, example=true)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         ),
     *         
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="string", maxLength=255, example="Complete Adobe Illustrator Course 2024"),
     *                 @OA\Property(property="description", type="string", example="Master Adobe Illustrator from beginner to advanced"),
     *                 @OA\Property(property="thumbnail_url", type="string", format="binary", description="Course thumbnail image (jpeg, jpg, png, gif, max 5MB)"),
     *                 @OA\Property(property="subject_id", type="integer", example=1),
     *                 @OA\Property(property="price", type="number", format="float", example=149.99),
     *                 @OA\Property(property="old_price", type="number", format="float", example=299.99),
     *                 @OA\Property(property="is_published", type="boolean", example=true),
     *                 
     *                 @OA\Property(property="modules[0][id]", type="integer", example=4, description="Module ID for update"),
     *                 @OA\Property(property="modules[0][title]", type="string", example="Module 1 Updated"),
     *                 @OA\Property(property="modules[0][description]", type="string", example="Updated description"),
     *                 @OA\Property(property="modules[0][order_index]", type="integer", example=1),
     *                 
     *                 @OA\Property(property="modules[0][videos][0][id]", type="integer", example=5, description="Video ID for update"),
     *                 @OA\Property(property="modules[0][videos][0][title]", type="string", example="Existing Video Updated"),
     *                 @OA\Property(property="modules[0][videos][0][description]", type="string", example="This video already exists"),
     *                 @OA\Property(property="modules[0][videos][0][duration_hours]", type="number", format="float", example=3.5),
     *                 @OA\Property(property="modules[0][videos][0][is_published]", type="boolean", example=true),
     *                 @OA\Property(property="modules[0][videos][0][file]", type="string", format="binary", description="New video file (optional for update)"),
     *                 
     *                 @OA\Property(property="modules[0][videos][1][title]", type="string", example="Brand New Video", description="No ID means create new"),
     *                 @OA\Property(property="modules[0][videos][1][description]", type="string", example="This is a completely new video"),
     *                 @OA\Property(property="modules[0][videos][1][duration_hours]", type="number", format="float", example=2.0),
     *                 @OA\Property(property="modules[0][videos][1][is_published]", type="boolean", example=true),
     *                 @OA\Property(property="modules[0][videos][1][file]", type="string", format="binary", description="Video file (required for new videos, mp4/avi/mov, max 100MB)"),
     *                 
     *                 @OA\Property(property="modules[1][title]", type="string", example="New Module", description="No ID means create new module"),
     *                 @OA\Property(property="modules[1][description]", type="string", example="A brand new module"),
     *                 @OA\Property(property="modules[1][order_index]", type="integer", example=2)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Course updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Course updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="course",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Complete Adobe Illustrator Course 2024"),
     *                     @OA\Property(property="description", type="string", example="Master Adobe Illustrator from beginner to advanced"),
     *                     @OA\Property(property="thumbnail_url", type="string", example="thumbnails/1234567890_abc123.jpg"),
     *                     @OA\Property(property="subject_id", type="integer", example=1),
     *                     @OA\Property(property="price", type="number", format="float", example=149.99),
     *                     @OA\Property(property="old_price", type="number", format="float", example=299.99),
     *                     @OA\Property(property="is_published", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-20T14:45:00.000000Z"),
     *                     
     *                     @OA\Property(
     *                         property="subject",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Design")
     *                     ),
     *                     
     *                     @OA\Property(
     *                         property="modules",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="course_id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="Introduction to Illustrator"),
     *                             @OA\Property(property="description", type="string", example="Learn the basics"),
     *                             @OA\Property(property="order_index", type="integer", example=1),
     *                             @OA\Property(property="created_at", type="string", format="date-time"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time"),
     *                             
     *                             @OA\Property(
     *                                 property="video_lessons",
     *                                 type="array",
     *                                 @OA\Items(
     *                                     type="object",
     *                                     @OA\Property(property="id", type="integer", example=5),
     *                                     @OA\Property(property="module_id", type="integer", example=4),
     *                                     @OA\Property(property="title", type="string", example="Getting Started"),
     *                                     @OA\Property(property="description", type="string", example="Introduction to the interface"),
     *                                     @OA\Property(property="duration_hours", type="number", format="float", example=2.5),
     *                                     @OA\Property(property="video_path", type="string", example="videos/1234567890_xyz789.mp4"),
     *                                     @OA\Property(property="filename", type="string", example="1234567890_xyz789.mp4"),
     *                                     @OA\Property(property="file_size", type="integer", example=104857600),
     *                                     @OA\Property(property="mime_type", type="string", example="video/mp4"),
     *                                     @OA\Property(property="is_published", type="boolean", example=true),
     *                                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="modules",
     *                     type="array",
     *                     description="Array of processed modules",
     *                     @OA\Items(type="object")
     *                 ),
     *                 
     *                 @OA\Property(
     *                     property="videos",
     *                     type="array",
     *                     description="Array of processed videos",
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Course not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Update failed: No query results for model [App\\Models\\Course] 999")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The title field is required. (and 2 more errors)"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="title",
     *                     type="array",
     *                     @OA\Items(type="string", example="The title field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="price",
     *                     type="array",
     *                     @OA\Items(type="string", example="The price must be at least 0.")
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Update failed: Video file is required when creating new videos")
     *         )
     *     )
     * )
     */

    public function update(Request $request, $id)
    {
        // Validate request
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'thumbnail_url' => 'nullable|file|image|mimes:jpeg,jpg,png,gif|max:5120',
            'subject_id' => 'sometimes|exists:subjects,id',
            'price' => 'sometimes|numeric|min:0',
            'old_price' => 'nullable|numeric|min:0',
            'is_published' => 'nullable|boolean',

            // Modules validation
            'modules' => 'sometimes|array|min:1',
            'modules.*.id' => 'nullable|integer',
            'modules.*.title' => 'sometimes|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.order_index' => 'sometimes|integer|min:1',

            // Videos
            'modules.*.videos' => 'nullable|array',
            'modules.*.videos.*.id' => 'nullable|integer',
            'modules.*.videos.*.title' => 'sometimes|string|max:255',
            'modules.*.videos.*.description' => 'nullable|string',
            'modules.*.videos.*.duration_hours' => 'nullable|numeric|min:0',
            'modules.*.videos.*.file' => 'nullable|file|mimetypes:video/mp4,video/avi,video/mov,video/quicktime|max:102400',
            'modules.*.videos.*.is_published' => 'nullable|boolean',
        ]);

        DB::beginTransaction();

        try {
            $course = Course::findOrFail($id);

            /** ---------------------------------------------------------
             * 1. COURSE UPDATE
             * -------------------------------------------------------- */
            $course->fill([
                'title' => $validated['title'] ?? $course->title,
                'description' => $validated['description'] ?? $course->description,
                'subject_id' => $validated['subject_id'] ?? $course->subject_id,
                'price' => $validated['price'] ?? $course->price,
                'old_price' => $validated['old_price'] ?? $course->old_price,
                'is_published' => $request->is_published ?? $course->is_published,
            ]);

            // Thumbnail replace
            if ($request->hasFile('thumbnail_url')) {
                if ($course->thumbnail_url && Storage::disk('public')->exists($course->thumbnail_url)) {
                    Storage::disk('public')->delete($course->thumbnail_url);
                }

                $thumb = $request->file('thumbnail_url');
                $thumbName = time() . '_' . uniqid() . '.' . $thumb->getClientOriginalExtension();
                $thumbPath = $thumb->storeAs('thumbnails', $thumbName, 'public');

                $course->thumbnail_url = $thumbPath;
            }

            $course->save();


            /** ---------------------------------------------------------
             * 2. MODULES UPDATE + CREATE + DELETE
             * -------------------------------------------------------- */
            $existingModuleIds = $course->modules()->pluck('id')->toArray();
            $incomingModuleIds = [];
            
            if (!empty($validated['modules'])) {
                $incomingModuleIds = array_filter(array_column($validated['modules'], 'id'));
            }

            // Delete removed modules
            $modulesToDelete = array_diff($existingModuleIds, $incomingModuleIds);
            if (!empty($modulesToDelete)) {
                Module::whereIn('id', $modulesToDelete)->delete();
            }

            $finalModules = [];
            $finalVideos = [];

            if (!empty($validated['modules'])) {
                foreach ($validated['modules'] as $moduleData) {

                    // UPDATE or CREATE
                    if (!empty($moduleData['id'])) {
                        $module = Module::find($moduleData['id']);
                        
                        if (!$module) {
                            throw new \Exception("Module with ID {$moduleData['id']} not found");
                        }
                        
                        $module->update([
                            'title' => $moduleData['title'] ?? $module->title,
                            'description' => $moduleData['description'] ?? $module->description,
                            'order_index' => $moduleData['order_index'] ?? $module->order_index,
                        ]);
                    } else {
                        $module = Module::create([
                            'course_id' => $course->id,
                            'title' => $moduleData['title'],
                            'description' => $moduleData['description'] ?? null,
                            'order_index' => $moduleData['order_index'] ?? 1,
                        ]);
                    }

                    $finalModules[] = $module;


                    /** -----------------------------------------------------
                     * 3. VIDEOS UPDATE + CREATE + DELETE
                     * ---------------------------------------------------- */
                    $existingVideoIds = $module->videoLessons()->pluck('id')->toArray();
                    $incomingVideoIds = [];

                    if (!empty($moduleData['videos'])) {
                        $incomingVideoIds = array_filter(array_column($moduleData['videos'], 'id'));
                    }

                    // Delete removed videos
                    $videosToDelete = array_diff($existingVideoIds, $incomingVideoIds);
                    if (!empty($videosToDelete)) {
                        foreach ($videosToDelete as $vid) {
                            $v = VideoLesson::find($vid);
                            if ($v) {
                                if ($v->video_path && Storage::disk('public')->exists($v->video_path)) {
                                    Storage::disk('public')->delete($v->video_path);
                                }
                                $v->delete();
                            }
                        }
                    }

                    // Handle create / update videos
                    if (!empty($moduleData['videos'])) {
                        foreach ($moduleData['videos'] as $videoData) {

                            // ---------------- UPDATE EXISTING VIDEO ----------------
                            if (!empty($videoData['id'])) {
                                $videoLesson = VideoLesson::find($videoData['id']);

                                if (!$videoLesson) {
                                    throw new \Exception("Video with ID {$videoData['id']} not found");
                                }

                                // Replace file if new uploaded
                                if (!empty($videoData['file']) && $videoData['file'] instanceof \Illuminate\Http\UploadedFile) {
                                    if ($videoLesson->video_path && Storage::disk('public')->exists($videoLesson->video_path)) {
                                        Storage::disk('public')->delete($videoLesson->video_path);
                                    }

                                    $file = $videoData['file'];
                                    $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                                    $filePath = $file->storeAs('videos', $fileName, 'public');

                                    $videoLesson->video_path = $filePath;
                                    $videoLesson->filename = $fileName;
                                    $videoLesson->file_size = $file->getSize();
                                    $videoLesson->mime_type = $file->getMimeType();
                                }

                                // Update other fields
                                $videoLesson->title = $videoData['title'] ?? $videoLesson->title;
                                $videoLesson->description = $videoData['description'] ?? $videoLesson->description;
                                $videoLesson->duration_hours = $videoData['duration_hours'] ?? $videoLesson->duration_hours;
                                $videoLesson->is_published = $videoData['is_published'] ?? $videoLesson->is_published;
                                $videoLesson->save();

                            } else {
                                // ---------------- CREATE NEW VIDEO ----------------
                                // Check if file exists for new video
                                if (empty($videoData['file']) || !($videoData['file'] instanceof \Illuminate\Http\UploadedFile)) {
                                    throw new \Exception('Video file is required when creating new videos');
                                }

                                $file = $videoData['file'];
                                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                                $filePath = $file->storeAs('videos', $fileName, 'public');

                                $videoLesson = VideoLesson::create([
                                    'module_id' => $module->id,
                                    'title' => $videoData['title'],
                                    'description' => $videoData['description'] ?? null,
                                    'duration_hours' => $videoData['duration_hours'] ?? 0,
                                    'video_path' => $filePath,
                                    'filename' => $fileName,
                                    'file_size' => $file->getSize(),
                                    'mime_type' => $file->getMimeType(),
                                    'is_published' => $videoData['is_published'] ?? true,
                                ]);
                            }

                            $finalVideos[] = $videoLesson;
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully.',
                'data' => [
                    'course' => $course->load(['subject', 'modules.videoLessons']),
                    'modules' => $finalModules,
                    'videos' => $finalVideos
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/courses/{id}",
     *     tags={"Courses"},
     *     summary="Delete a course",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Course ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Course deleted successfully"),
     *     @OA\Response(response=404, description="Course not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
    */

    public function destroy(Course $course)
    {
        DB::beginTransaction();

        try {
            $course->load('modules.videoLessons');
            $firebaseService = app(FirebaseService::class);
            // Delete modules and their videos
            foreach ($course->modules as $module) {
                foreach ($module->videoLessons as $video) {
                    // Delete video file from storage
                    if ($video->video_path) {
                        $firebaseService->deleteFile($video->video_path);
                    }
                    $video->delete();
                }
                $module->delete();
            }

            // Delete course thumbnail if exists
            if ($course->thumbnail_url && Storage::disk('public')->exists($course->thumbnail_url)) {
                Storage::disk('public')->delete($course->thumbnail_url);
            }

            // Delete course
            $course->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Course and all related modules/videos deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Delete failed: ' . $e->getMessage(),
            ], 500);
        }
    }

}
