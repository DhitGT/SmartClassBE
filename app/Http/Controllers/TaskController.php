<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\MemberModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\TaskSubjectModel;
use App\Models\TaskAttachmentModel;
use Exception;


class TaskController extends Controller
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
        if ($user->role == 'Teacher' || $user->role == 'Secretary' || $user->role == 'Member' || $user->role == 'Treasurer') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }

        return $class;
    }

    public function addTask(Request $req)
    {
        try {
            // Ensure the authenticated user exists
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $class =  $this->getUserClass($user, ['Leader', 'Secretary']);
            if ($class instanceof \Illuminate\Http\JsonResponse) {
                return $class; // 🔁 Immediately return the response, breaking the flow
            }

            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }

            // Validate incoming request data
            $validated = $req->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'due_to' => 'required|date',
                'subject_id' => 'required|string',
                'status' => 'required|in:ToDo,InProgress,Completed',
                'files' => 'nullable|array',
                'files.*' => 'file|mimes:jpeg,png,jpg,gif,pdf|max:5120', // Max 5MB
            ]);

            // Create new task
            $newTask = TaskSubjectModel::create([
                'id' => Str::uuid(),
                'class_id' => $class->id,
                'subject_id' => $validated['subject_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'due_to' => $validated['due_to'],
                'status' => $validated['status'],
            ]);

            // Handle file attachments
            if ($req->hasFile('files')) {
                foreach ($req->file('files') as $file) {
                    if ($file->isValid()) {
                        $filename = time() . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('attachments', $filename, 'public');

                        TaskAttachmentModel::create([
                            'id' => Str::uuid(),
                            'class_id' => $class->id,
                            'task_id' => $newTask->id,
                            'name' => $file->getClientOriginalName(),
                            'path' => $filePath,
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => 'Task created successfully',
                'messageType' => 'success',
                'task' => [
                    'id' => $newTask->id,
                    'name' => $newTask->name,
                    'class_id' => $newTask->class_id,
                    'subject_id' => $newTask->subject_id,
                    'description' => $newTask->description,
                    'due_to' => $newTask->due_to,
                    'status' => $newTask->status,
                    'attachments' => $newTask->attachments->map(function ($attachment) {
                        return [
                            'id' => $attachment->id,
                            'name' => $attachment->name,
                            'path' => asset('storage/' . $attachment->path),
                        ];
                    }),
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'messageType' => 'error',
            ], 500);
        }
    }

    public function getTask(Request $req)
    {
        try {
            // Ensure the authenticated user exists
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $class =  $this->getUserClass($user, ['Teacher','Leader', 'Member', 'Treasurer', 'Secretary']);
            if ($class instanceof \Illuminate\Http\JsonResponse) {
                return $class; // 🔁 Immediately return the response, breaking the flow
            }

            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }

            // Retrieve all tasks for the class including related subject data
            $tasks = TaskSubjectModel::where('class_id', $class->id)
                ->with('attachments', 'subject'); // Include attachments and subject data


            // Check if there is a search term
            if ($req->search) {
                $searchTerm = $req->search;
                $tasks->where(function ($query) use ($searchTerm) {
                    $query->where('name', 'LIKE', "%{$searchTerm}%") // Search in tasks.name
                        ->orWhereHas('subject', function ($q) use ($searchTerm) {
                            $q->where('name', 'LIKE', "%{$searchTerm}%"); // Search in subject.name
                        });
                });
            }


            if ($req->subject) {
                $tasks->where('subject_id', $req->subject);
            }

            $tasks = $tasks->get();

            return response()->json([
                'message' => 'Tasks retrieved successfully',
                'messageType' => 'success',
                'tasks' => $tasks

            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'messageType' => 'error',
            ], 500);
        }
    }
    public function updateStatus(Request $req)
    {
        try {
            // Ensure the authenticated user exists
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $class =  $this->getUserClass($user, ['Leader', 'Secretary']);
            if ($class instanceof \Illuminate\Http\JsonResponse) {
                return $class; // 🔁 Immediately return the response, breaking the flow
            }

            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }

            $validated = $req->validate([
                'id' => 'required|string|max:255',
                'status' => 'required|in:ToDo,InProgress,Completed',
            ]);

            // Retrieve all tasks for the class including related subject data
            $tasks = TaskSubjectModel::where('id', $req->id)->first();

            $tasks->status = $req->status;
            $tasks->update();

            return response()->json([
                'message' => 'Tasks update successfully',
                'messageType' => 'success',
                'tasks' => $tasks

            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'messageType' => 'error',
            ], 500);
        }
    }
}
