<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\TeacherLevel;
use App\Models\User;
use App\Services\FirebaseService;
use App\Services\SlotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class TeacherController extends Controller
{
    protected $auth;

    public function __construct(FirebaseService $firebase)
    {
        $this->auth = $firebase->getAuth();
    }


    /**
     * @OA\Get(
     *      path="/api/teachers",
     *      operationId="getTeachersList",
     *      tags={"Teachers"},
     *      summary="Get list of all teachers",
     *      description="Returns all teachers. You can optionally filter teachers by skill.",
     *
     *      @OA\Parameter(
     *          name="skill",
     *          in="query",
     *          required=false,
     *          description="Filter teachers by skill (e.g., laravel, python)",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *      )
     * )
    */

    public function index(Request $request)
    {
        $skill = $request->query('skill');

        $teachers = User::role('teacher')
            ->with([
                'teacher',
                'teacher.reviews',
                'teacher.teacherLevel',
                'teacher.skills',
            ])
            ->when($skill, function ($query) use ($skill) {
                $query->whereHas('teacher.skills', function ($q) use ($skill) {
                    $q->where('name', 'LIKE', "%{$skill}%");
                });
            })
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $teachers
        ], 200);
    }




    /**
     * @OA\Post(
     *     path="/api/teachers",
     *     operationId="storeTeacher",
     *     tags={"Teachers"},
     *     summary="Create a new teacher",
     *     description="Creates a teacher and associated Firebase user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="title", type="string", example="Math Teacher")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Teacher created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    public function store(Request $request)
    {
        // Validate input
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
            'title' => 'nullable|string|max:255',
        ]);
        DB::beginTransaction();

        // Check if user already exists
        $user = User::where('email', $data['email'])->first();
        $isNewUser = false;
        $firebaseUid = null;

        if (!$user) {
            $isNewUser = true;

            $firebaseUser = $this->auth->createUser([
                'email' => $data['email'],
                'password' => $data['password'],
                'displayName' => $data['name'],
            ]);

            $firebaseUid = $firebaseUser->uid;
            
            // Create local user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'firebase_uid' => $firebaseUid,
                'password' => bcrypt($data['password'])
            ]);
        } 

        $existingTeacher = Teacher::where('user_id', $user->id)->first();
        
        if ($existingTeacher) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'This user is already registered as a teacher.',
                'data' => $existingTeacher->load('user'),
            ], 409);
        }

        if (!$user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'title' => $data['title'] ?? null,
            'teacher_level_id' => 1,
            'base_pay' => 20.00,
        ]);

        // trigger email verification if needed
        event(new Registered($user));

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => $isNewUser 
                ? 'Teacher created successfully.' 
                : 'Existing user converted to teacher successfully.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => $teacher->load('user'),
        ], 201);
    }


    /**
     * @OA\Get(
     *     path="/api/teachers/{id}",
     *     operationId="showTeacher",
     *     tags={"Teachers"},
     *     summary="Get teacher by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Teacher ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Teacher not found")
     * )
     */
    public function show(Teacher $teacher)
    {
        return response()->json($teacher->load(['user', 'teacherLevel']));
    }


    /**
     * @OA\Put(
     *     path="/api/teachers/{id}",
     *     operationId="updateTeacher",
     *     tags={"Teachers"},
     *     summary="Update teacher info",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="introduction_video", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="bio", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="profile_picture", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Teacher updated successfully"),
     *     @OA\Response(response=404, description="Teacher not found")
     * )
     */
    public function update(Request $request, Teacher $teacher)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required','email','max:255',Rule::unique('users')->ignore($teacher->id)],
            'title' => 'nullable|string|max:255',
            'introduction_video' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string',
            'address' => 'nullable|string',
            'profile_picture' => 'nullable|string',
        ]);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? $teacher->user->phone,
            'bio' => $data['bio'] ?? $teacher->user->bio,
            'address' => $data['address'] ?? $teacher->user->address,
            'profile_picture' => $data['profile_picture'] ?? $teacher->user->profile_picture,
        ];
        $teacherData = [
            'title' => $data['title'] ?? $teacher->title,
            'introduction_video' => $data['introduction_video'] ?? $teacher->introduction_video,
        ];

        $teacher->user->update($userData);
        $teacher->update($teacherData);
        return response()->json([
            'message' => 'Teacher updated successfully.',
            'data' => $teacher->load('user'),
        ]);
    }


    /**
     * @OA\Delete(
     *     path="/api/teachers/{id}",
     *     operationId="deleteTeacher",
     *     tags={"Teachers"},
     *     summary="Delete a teacher",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Teacher deleted successfully"),
     *     @OA\Response(response=404, description="Teacher not found")
     * )
     */
    public function destroy(Teacher $teacher)
    {
        $user = $teacher->user;
        $teacher->delete();
        if ($user) {
            $user->removeRole('teacher');
            $user->delete();  
        }  
        return response()->json(['message' => 'Teacher deleted successfully']);

    }




    /**
     * @OA\Get(
     *     path="/api/teachers/{id}/details",
     *     summary="Get full details of a specific teacher",
     *     description="Returns teacher information including skills, courses, available online slots, and available in-person slots.",
     *     tags={"Teachers"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Teacher user ID",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *
     *             @OA\Property(
     *                 property="teacher",
     *                 type="object",
     *                 description="Teacher main information",
     *             ),
     *
     *             @OA\Property(
     *                 property="available_slots",
     *                 type="array",
     *                 description="Formatted online available slots",
     *                 @OA\Items(
     *                     type="object",
     *                     example={
     *                         "id": 12,
     *                         "title": "Math Class",
     *                         "subject_id": 3,
     *                         "from_date": "2025-02-20",
     *                         "to_date": "2025-03-01",
     *                         "start_time": "10:00:00",
     *                         "end_time": "11:00:00",
     *                         "type": "online",
     *                         "price": 500,
     *                         "description": "Basic math topics",
     *                         "daily_available_seats": {
     *                             "2025-02-20": {"booked": 1, "available": 4},
     *                             "2025-02-21": {"booked": 0, "available": 5}
     *                         }
     *                     }
     *                 )
     *             ),
     *
     *             @OA\Property(
     *                 property="in_person_slots",
     *                 type="array",
     *                 description="Formatted in-person slots",
     *                 @OA\Items(
     *                     type="object",
     *                     example={
     *                         "id": 7,
     *                         "title": "Dhaka Batch Training",
     *                         "subject_id": 2,
     *                         "from_date": "2025-02-15",
     *                         "to_date": "2025-02-18",
     *                         "start_time": "14:00:00",
     *                         "end_time": "16:00:00",
     *                         "type": "in_person",
     *                         "price": 1500,
     *                         "description": "In-person training session",
     *                         "location": {
     *                             "country": "Bangladesh",
     *                             "state": "Dhaka",
     *                             "city": "Mirpur",
     *                             "full_address": "Mirpur DOHS Road 18"
     *                         }
     *                     }
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Teacher not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Teacher not found")
     *         )
     *     )
     * )
    */


    public function teacherDetails($id)
    {
        $teacher = User::with([
                'teacher',
                'teacher.skills',
                'teacher.courses',
                'teacher.availableSlots',
                'teacher.inPersonSlots',
            ])->findOrFail($id);

        // Format available slots
        $formattedOnlineSlots = $teacher->teacher->availableSlots
            ->map(fn($slot) => SlotService::formatSlot($slot));

        // Format in-person slots
        $formattedInPersonSlots = $teacher->teacher->inPersonSlots
            ->map(fn($slot) => SlotService::formatSlot($slot));

        return response()->json([
            'status' => 'success',
            'teacher' => $teacher,
            'available_slots' => $formattedOnlineSlots,
            'in_person_slots' => $formattedInPersonSlots,
        ]);
    }



    /**
     * @OA\Post(
     *     path="/api/teachers/select-main-courses",
     *     summary="Select main courses for a teacher",
     *     description="Allows an authenticated teacher to mark specific courses as main courses. First resets all to is_main=false, then marks selected ones as true.",
     *     tags={"Teachers"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 @OA\Items(type="integer", example=3),
     *                 description="Array of course IDs to set as main"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Main courses updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Main courses updated successfully.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
    */
    public function selectMainCourses(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:courses,id',
        ]);

        $user = Auth::user();

        $teacherCourseIds = Course::where('teacher_id', $user->id)->pluck('id')->toArray();

        Course::whereIn('id', $teacherCourseIds)->update(['is_main' => false]);

        Course::whereIn('id', $data['ids'])->whereIn('id', $teacherCourseIds)->update(['is_main' => true]);


        return response()->json([
            'success' => true,
            'message' => 'Main courses updated successfully.'
        ]);
    }




}
