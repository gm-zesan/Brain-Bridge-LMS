<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubjectController extends Controller
{

    /**
     * @OA\Get(
     *      path="/api/subjects",
     *      operationId="getSubjectsList",
     *      tags={"Subjects"},
     *      summary="Get list of all subjects",
     *      description="Returns all subjects from the database",
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *      )
     * )
     */
    public function index()
    {
        return response()->json(Subject::with('parent')->get());
    }

    /**
     * @OA\Post(
     *     path="/api/subjects",
     *     operationId="storeSubject",
     *     tags={"Subjects"},
     *     summary="Create a new subject",
     *     description="Creates a new subject",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Mathematics"),
     *             @OA\Property(property="parent_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Subject created successfully"),
     * )
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:subjects,id',
            'base_pay' => 'nullable|numeric|min:0',
            'icon' => 'nullable|file|mimes:jpeg,jpg,png,gif,svg|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            $icon = $request->file('icon');
            $iconName = time() . '_' . uniqid() . '.' . $icon->getClientOriginalExtension();
            $iconPath = $icon->storeAs('icons', $iconName, 'public');
            $data['icon'] = $iconPath;
        }

        $subject = Subject::create($data);
        return response()->json($subject, 201);
    }

    /**
     * @OA\Get(
     *      path="/api/subjects/{id}",
     *      operationId="getSubjectById",
     *      tags={"Subjects"},
     *      summary="Get subject information",
     *      description="Returns subject data",
     *      @OA\Parameter(
     *          name="id",
     *          description="Subject id",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="integer"
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation"
     *      ),
     *      @OA\Response(
     *          response=404,
     *          description="Subject not found"
     *      )
     * )
     */
    public function show(Subject $subject)
    {
        return response()->json($subject->load('parent'));
    }

    /**
     * @OA\Put(
     *     path="/api/subjects/{id}",
     *     operationId="updateSubject",
     *     tags={"Subjects"},
     *     summary="Update an existing subject",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Subject ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Physics"),
     *             @OA\Property(property="parent_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Subject updated successfully"),
     *     @OA\Response(response=404, description="Subject not found")
     * )
     */
    public function update(Request $request, Subject $subject)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'parent_id' => 'nullable|exists:subjects,id',
            'base_pay' => 'nullable|numeric|min:0',
            'icon' => 'nullable|file|mimes:jpeg,jpg,png,gif,svg|max:2048',
        ]);

        if ($request->hasFile('icon')) {
            if ($subject->icon && Storage::disk('public')->exists($subject->icon)) {
                Storage::disk('public')->delete($subject->icon);
            }
            $icon = $request->file('icon');
            $iconName = time() . '_' . uniqid() . '.' . $icon->getClientOriginalExtension();
            $iconPath = $icon->storeAs('icons', $iconName, 'public');
            $data['icon'] = $iconPath;
        }

        $subject->update($data);
        return response()->json($subject);
    }

    /**
     * @OA\Delete(
     *     path="/api/subjects/{id}",
     *     operationId="deleteSubject",
     *     tags={"Subjects"},
     *     summary="Delete a subject",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Subject ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Subject deleted successfully"),
     *     @OA\Response(response=404, description="Subject not found")
     * )
     */
    public function destroy(Subject $subject)
    {
        if ($subject->icon && Storage::disk('public')->exists($subject->icon)) {
            Storage::disk('public')->delete($subject->icon);
        }
        $subject->delete();
        return response()->json(['message' => 'Subject deleted successfully']);
    }
}
