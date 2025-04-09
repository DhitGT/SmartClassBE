<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\MemberModel;
use App\Models\SubjectModel;
use App\Models\TaskAttachmentModel;
use App\Models\TaskSubjectModel;
use Illuminate\Support\Facades\Storage;
use App\Models\TeacherModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubjectController extends Controller
{
    public function getUserClass($user, $grantRole)
    {
        $class =  new ClassModel();

        if (!in_array($user->role, $grantRole)) {
            return response()->json([
                'message' => 'Permission denied',
                'messageType' => 'error',
                'user' => $user,
                'grant' => !in_array($user->role, $grantRole),
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

    public function addSubject(Request $req)
    {
        // Ensure the authenticated user exists
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  $this->getUserClass($user, ['Leader', 'Secretary']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }

        // if ($user->role == 'Leader') {
        //     $class = ClassModel::where('leader_id', $user->id)->first();
        // } else if ($user->role == 'Secretary') {
        //     $memberData = MemberModel::where('user_id', $user->id)->first();
        //     $class = ClassModel::where('id', $memberData->class_id)->first();
        // }



        // Validate incoming request data
        $validated = $req->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string',
            'description' => 'required|string',
        ]);


        // Create new user
        $newSubject = SubjectModel::create([
            'class_id' => $class->id,
            'name' => $validated['name'],
            'description' => $validated['description'],
            'icon' => $validated['icon'],
        ]);


        return response()->json([
            'message' => 'Subject created successfully',
            'messageType' => 'success',
            'subject' => [
                'id' => $newSubject->id,
                'name' => $newSubject->name,
                'description' => $newSubject->description,
                'icon' => $newSubject->icon,
            ],
        ], 201);
    }


    public function editSubject(Request $req)
    {
        $id = $req->id;
        // Ensure the authenticated user exists
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Find the subject to edit
        $subject = SubjectModel::find($id);
        if (!$subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }

        // Get the class based on user role
        $class = $this->getUserClass($user, ['Leader', 'Secretary']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }

        // Check if the subject belongs to the user's class
        if ($subject->class_id != $class->id) {
            return response()->json(['message' => 'Unauthorized to edit this subject'], 403);
        }

        // Validate incoming request data
        $validated = $req->validate([
            'name' => 'required|string|max:255',
            'icon' => 'required|string',
            'description' => 'required|string',
        ]);

        // Update the subject
        $subject->name = $validated['name'];
        $subject->description = $validated['description'];
        $subject->icon = $validated['icon'];
        $subject->save();

        return response()->json([
            'message' => 'Subject updated successfully',
            'messageType' => 'success',
            'subject' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'description' => $subject->description,
                'icon' => $subject->icon,
            ],
        ], 200);
    }

    public function getSubject(Request $req)
    {
        // Ensure the authenticated user exists
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  $this->getUserClass($user, ['Leader', 'Secretary', 'Member', 'Treasurer']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }

        // if ($user->role == 'Leader') {
        //     $class = ClassModel::where('leader_id', $user->id)->first();
        // } else if ($user->role == 'Secretary') {
        //     $memberData = MemberModel::where('user_id', $user->id)->first();
        //     $class = ClassModel::where('id', $memberData->class_id)->first();
        // }

        $subject = SubjectModel::where('class_id', $class->id)
            ->withCount('task')
            ->with([
                'teacher' => function ($query) {
                    $query->select('id', 'subject_id', 'name', 'avatar'); // Select only necessary columns
                },
                'task' => function ($query) {
                    $query->select('id', 'subject_id'); // Adjust as needed
                }
            ])
            ->get();


        return response()->json([
            'message' => 'Subject get successfully',
            'messageType' => 'success',
            'subject' => $subject,
        ], 201);
    }



    public function deleteSubject(Request $req)
    {
        $id = $req->id;

        // Ensure the authenticated user exists
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Find the subject to delete
        $subject = SubjectModel::find($id);
        if (!$subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }

        // Get the class based on user role
        $class =  $this->getUserClass($user, ['Leader', 'Secretary']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }

        // if ($user->role == 'Leader') {
        //     $class = ClassModel::where('leader_id', $user->id)->first();
        // } else if ($user->role == 'Secretary') {
        //     $memberData = MemberModel::where('user_id', $user->id)->first();
        //     if ($memberData) {
        //         $class = ClassModel::where('id', $memberData->class_id)->first();
        //     }
        // }

        if (!$class || $subject->class_id != $class->id) {
            return response()->json(['message' => 'Unauthorized to delete this subject'], 403);
        }

        // Retrieve all TaskSubject records related to this subject
        $taskSubjects = TaskSubjectModel::where('subject_id', $id)->get();

        foreach ($taskSubjects as $taskSubject) {
            // Retrieve all attachments related to the task subject
            $attachments = TaskAttachmentModel::where('task_id', $taskSubject->task_id)->get();

            // Delete files from storage
            foreach ($attachments as $attachment) {
                Storage::disk('public')->delete($attachment->path);
            }

            // Delete the related attachments from the database
            TaskAttachmentModel::where('task_id', $taskSubject->task_id)->delete();
        }

        // Delete all TaskSubject records related to the subject
        TaskSubjectModel::where('subject_id', $id)->delete();

        // Delete the subject
        $subject->delete();

        return response()->json([
            'message' => 'Subject and related tasks deleted successfully',
            'messageType' => 'success'
        ], 200);
    }
}
