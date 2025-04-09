<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClassModel;
use App\Models\MemberModel;
use App\Models\TeacherModel;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;


class TeacherController extends Controller
{
    public function getUserClass($user, $grantRole)
    {
        $class =  new ClassModel();

        if (!in_array($user->role, $grantRole)) {
            return response()->json([
                'message' => 'Permission denied',
                'messageType' => 'error',
            ], 200);
        }

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        }
        if ($user->role == 'Secretary' || $user->role == 'Member' || $user->role == 'Treasurer') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }

        return $class;
    }
    //
    public function addTeacher(Request $req)
    {
        try {
            //code...
            // Ensure the authenticated user exists
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $class =  $this->getUserClass($user, ['Leader', 'Secretary']);
            if ($class instanceof \Illuminate\Http\JsonResponse) {
                return $class; // 🔁 Immediately return the response, breaking the flow
            }

            // Validate incoming request data
            $validated = $req->validate([
                'name' => 'required|string|max:255',
                'subject_id' => 'required|string',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:3024', // Max 1MB
            ]);

            // Handle avatar upload
            $avatarPath = null;
            if ($req->hasFile('avatar') && $req->file('avatar')->isValid()) {
                $avatar = $req->file('avatar');
                $filename = time() . '_' . $avatar->getClientOriginalName();
                $avatarPath = $avatar->storeAs('avatars', $filename, 'public');
            }

            // Create new user
            $newTeacher = TeacherModel::create([
                'name' => $validated['name'],
                'class_id' => $class->id,
                'subject_id' => $validated['subject_id'],
                'avatar' => $avatarPath, // Store the path to the uploaded avatar
            ]);


            return response()->json([
                'message' => 'Teacher created successfully',
                'messageType' => 'success',
                'teacher' => [
                    'id' => $newTeacher->id,
                    'name' => $newTeacher->name,
                    'class_id' => $newTeacher->class_id,
                    'subject_id' => $newTeacher->subject_id,
                    'avatar' => $avatarPath ? asset('storage/' . $avatarPath) : null,
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'messageType' => 'error',

            ], 200);
            //throw $th;
        }
    }

    public function editTeacher(Request $req)
    {
        try {

            $id = $req->id;
            // Ensure the authenticated user exists
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $class =  $this->getUserClass($user, ['Leader', 'Secretary']);
            if ($class instanceof \Illuminate\Http\JsonResponse) {
                return $class; // 🔁 Immediately return the response, breaking the flow
            }

            // Find the teacher to edit
            $teacher = TeacherModel::where('id', $id)
                ->where('class_id', $class->id)
                ->first();

            if (!$teacher) {
                return response()->json(['message' => 'Teacher not found or you do not have permission to edit this teacher'], 404);
            }

            // Validate incoming request data
            $validated = $req->validate([
                'name' => 'sometimes|required|string|max:255',
                'subject_id' => 'sometimes|required|string',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:3024', // Max 3MB
            ]);

            // Handle avatar upload
            if ($req->hasFile('avatar') && $req->file('avatar')->isValid()) {
                // Delete old avatar if it exists
                if ($teacher->avatar) {
                    Storage::disk('public')->delete($teacher->avatar);
                }

                $avatar = $req->file('avatar');
                $filename = time() . '_' . $avatar->getClientOriginalName();
                $avatarPath = $avatar->storeAs('avatars', $filename, 'public');

                $teacher->avatar = $avatarPath;
            }

            // Update teacher details
            if (isset($validated['name'])) {
                $teacher->name = $validated['name'];
            }

            if (isset($validated['subject_id'])) {
                $teacher->subject_id = $validated['subject_id'];
            }

            $teacher->save();

            return response()->json([
                'message' => 'Teacher updated successfully',
                'messageType' => 'success',
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'class_id' => $teacher->class_id,
                    'subject_id' => $teacher->subject_id,
                    'avatar' => $teacher->avatar ? asset('storage/' . $teacher->avatar) : null,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'messageType' => 'error',

            ], 200);
            //throw $th;
        }
    }


    public function getTeacher(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  $this->getUserClass($user, ['Leader', 'Member', 'Treasurer', 'Secretary']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }

        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        // Get search and filter inputs
        $search = $req->input('search');
        $role = $req->input('role');
        $perPage = $req->input('per_page', 10); // Default 10 per page
        $page = $req->input('page', 1); // Get page number

        $teacherQuery = TeacherModel::where('class_id', $class->id)
            ->with(['subject' => function ($query) {
                $query->select('*'); // Fetch all fields
            }]);

        // Apply search filter
        if ($search) {
            $teacherQuery->whereHas('subject', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%');
                // ->orWhere('email', 'like', '%' . $search . '%');
            })->orWhere('name', 'like', '%' . $search . '%');
        }

        // Apply role filter - filter on the user model
        // if ($role) {
        //     $teacherQuery->whereHas('subject', function ($query) use ($role) {
        //         $query->where('role', $role);
        //     });
        // }

        // Paginate results
        $teachers = $teacherQuery->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'message' => 'User get successfully',
            'messageType' => 'success',
            'data' => [
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                ],
                'teachers' => $teachers
            ]
        ], 200);
    }

    public function deleteTeacher(Request $req)
    {

        try {
            //code...
            $id = $req->id;
            // Ensure the authenticated user exists
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Find the class led by the authenticated user

            $class =  $this->getUserClass($user, ['Leader', 'Secretary']);
            if ($class instanceof \Illuminate\Http\JsonResponse) {
                return $class; // 🔁 Immediately return the response, breaking the flow
            }

            if (!$class) {
                return response()->json(['message' => 'You are not authorized to delete teachers'], 403);
            }

            // Find the teacher to delete
            $teacher = TeacherModel::where('id', $id)
                ->where('class_id', $class->id)
                ->first();

            if (!$teacher) {
                return response()->json(['message' => 'Teacher not found or you do not have permission to delete this teacher'], 404);
            }

            // Delete the avatar file if it exists
            if ($teacher->avatar) {
                Storage::disk('public')->delete($teacher->avatar);
            }

            // Delete the teacher
            $teacher->delete();

            return response()->json([
                'message' => 'Teacher deleted successfully',
                'messageType' => 'success'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'messageType' => 'error',

            ], 200);
            //throw $th;
        }
    }
}
