<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    protected $auth;

    public function __construct(FirebaseService $firebase)
    {
        $this->auth = $firebase->getAuth();
    }
    /**
     * @OA\Get(
     *      path="/api/students",
     *      operationId="getStudentsList",
     *      tags={"Students"},
     *      summary="Get list of all students",
     *      description="Returns all students from the database",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *      )
     * )
     */
    public function index()
    {
        $students = User::role('student')->get();
        return response()->json($students, 200);
    }


    /**
     * @OA\Post(
     *     path="/api/students",
     *     operationId="storeStudent",
     *     tags={"Students"},
     *     summary="Create a new student",
     *     description="Creates a student and associated Firebase user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@gmail.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="referral_code", type="string", example="ABCD1234")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Student created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
    */

    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'name' => 'required|string|max:255',
            'referral_code' => 'nullable|exists:users,referral_code',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        
        try {
            $firebaseUser = $this->auth->createUser([
                'email' => $request->email,
                'password' => $request->password,
                'displayName' => $request->name,
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'firebase_uid' => $firebaseUser->uid,
                'password' => bcrypt($request->password),
                'referred_by' => $request->referral_code ?? null,
                'date_of_birth' => $request->date_of_birth ?? null,
                'address' => $request->address ?? null,
            ]);

            // ---- REFERRAL LOGIC ----
            if ($request->referral_code) {

                $inviter = User::where('referral_code', $request->referral_code)->first();

                if ($inviter) {
                    $ref = Referral::create([
                        'referrer_id' => $inviter->id,
                        'referee_id' => $user->id,
                        'reward_given' => false,
                    ]);

                    // Give rewards (optimized)
                    $inviter->increment('points', 100);
                    $user->increment('points', 50);

                    // Mark as completed
                    $ref->update(['reward_given' => true]);
                }
            }

            $user->assignRole('student');

            // trigger email verification if needed
            event(new Registered($user));

            DB::commit();

            $token = $user->createToken('auth_token')->plainTextToken;
            return response()->json([
                'message' => 'Registration successful',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/students/{id}",
     *      operationId="getStudentById",
     *      tags={"Students"},
     *      summary="Get student information",
     *      @OA\Parameter(
     *          name="id",
     *          description="Student ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Student not found"
     *      )
     * )
     */
    public function show(User $student)
    {
        return response()->json($student, 200);
    }

    /**
     * @OA\Put(
     *      path="/api/students/{id}",
     *      operationId="updateStudent",
     *      tags={"Students"},
     *      summary="Update student information",
     *      @OA\Parameter(
     *          name="id",
     *          description="Student ID",
     *          required=true,
     *          in="path",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="name", type="string", example="Jane Doe"),
     *              @OA\Property(property="email", type="string", example="jane@gmail.com"),
     *              @OA\Property(property="phone", type="string", example="+19876543210"),
     *              @OA\Property(property="bio", type="string", example="This is my updated bio")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Student updated successfully"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Student not found"
     *      )
     * )
     */
    // public function update(Request $request, User $student)
    // {
    //     $data = $request->validate([
    //         'name' => 'sometimes|string|max:255',
    //         'email' => 'sometimes|string|email|max:255|unique:users,email,' . $student->id,
    //         'phone' => 'sometimes|string|max:20',
    //         'bio' => 'sometimes|string',
    //         'address' => 'sometimes|string',
    //         'date_of_birth' => 'sometimes|date',
    //         'profile_picture' => 'sometimes|string',
    //     ]);
    //     $student->update($data);
    //     return response()->json([
    //         'message' => 'Student updated successfully',
    //         'student' => $student
    //     ], 200);
    // }
    public function update(Request $request, User $student)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $student->id,
            'phone' => 'sometimes|string|max:20',
            'bio' => 'sometimes|string',
            'address' => 'sometimes|string',
            'date_of_birth' => 'sometimes|date',
            'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        /* ===== Profile Picture Upload ===== */
        if ($request->hasFile('profile_picture')) {

            // Delete old picture if exists
            if ($student->profile_picture && Storage::disk('public')->exists($student->profile_picture)) {
                Storage::disk('public')->delete($student->profile_picture);
            }

            $file = $request->file('profile_picture');
            $filename = uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profile_picture', $filename, 'public');

            $data['profile_picture'] = $path;
        }

        $student->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Student updated successfully',
            'student' => $student,
        ], 200);
    }
}
