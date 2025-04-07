<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use App\Models\MemberModel;
use App\Models\ScheduleDutyModel;
use App\Models\ScheduleSubjectModel;
use App\Models\SubjectModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    //

    function GetClassSubjectSchedule(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  new ClassModel();

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }


        $schedules = ScheduleSubjectModel::where('class_id', $class->id)
            ->with('subject')
            ->orderBy('start_time', 'asc')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'day' => $schedule->day,
                    'subject_id' => $schedule->subject_id,
                    'start_time' => date('H:i', strtotime($schedule->start_time)),
                    'end_time' => date('H:i', strtotime($schedule->end_time)),
                    'class_id' => $schedule->class_id,
                    'created_at' => $schedule->created_at,
                    'updated_at' => $schedule->updated_at,

                    'subject' => $schedule->subject ? [
                        'id' => $schedule->subject->id,
                        'name' => $schedule->subject->name,
                        'icon' => $schedule->subject->icon,
                        'description' => $schedule->subject->description,
                        'class_id' => $schedule->subject->class_id,
                        'created_at' => $schedule->subject->created_at,
                        'updated_at' => $schedule->subject->updated_at,

                        // Inject schedule-related info into subject object
                        'schedule_id' => $schedule->id,
                        'start_time' => date('H:i', strtotime($schedule->start_time)),
                        'end_time' => date('H:i', strtotime($schedule->end_time)),
                    ] : null,
                ];
            });
        return response()->json([
            'message' => 'Schedule set successfully',
            'messageType' => 'success',
            'schedule' => $schedules,
        ], 201);
    }
    function GetClassDutySchedule(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  new ClassModel();

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }


        $schedules = ScheduleDutyModel::where('class_id', $class->id)
            ->with([
                'member' => function ($query) {
                    $query->select('id', 'user_id', 'class_id')
                        ->with(['user' => function ($q) {
                            $q->select('id', 'name', 'avatar');
                        }]);
                }
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'message' => 'Schedule set successfully',
            'messageType' => 'success',
            'schedule' => $schedules,
        ], 201);
    }

    public function SetSchedule(Request $req)
    {
        try {
            //code...
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // ✅ Validate input including time fields
            $validated = $req->validate([
                'day_number' => 'required|integer|min:1|max:7',
                'subject_id' => 'required',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
            ]);

            // ✅ Get class based on user role
            $class = null;
            if ($user->role === 'Leader') {
                $class = ClassModel::where('leader_id', $user->id)->first();
            } elseif ($user->role === 'Secretary') {
                $memberData = MemberModel::where('user_id', $user->id)->first();
                if ($memberData) {
                    $class = ClassModel::find($memberData->class_id);
                }
            }

            if (!$class) {
                return response()->json([
                    'message' => 'Class not found or not associated with the user',
                    'messageType' => 'error',
                ], 404);
            }

            // ✅ Create schedule with time

            $startTime = date('H:i', strtotime($validated['start_time']));
            $endTime = date('H:i', strtotime($validated['end_time']));

            $schedule = ScheduleSubjectModel::create([
                'day' => $validated['day_number'],
                'subject_id' => $validated['subject_id'],
                'class_id' => $class->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]);

            return response()->json([
                'message' => 'Schedule set successfully',
                'messageType' => 'success',
                'schedule' => $schedule,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()

            ], 201);
            //throw $th;
        }
    }
    public function SetDutySchedule(Request $req)
    {
        try {
            //code...
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // ✅ Validate input including time fields
            $validated = $req->validate([
                'day_number' => 'required|integer|min:1|max:7',
                'member_ids' => 'required',
            ]);

            // ✅ Get class based on user role
            $class = null;
            if ($user->role === 'Leader') {
                $class = ClassModel::where('leader_id', $user->id)->first();
            } elseif ($user->role === 'Secretary') {
                $memberData = MemberModel::where('user_id', $user->id)->first();
                if ($memberData) {
                    $class = ClassModel::find($memberData->class_id);
                }
            }

            if (!$class) {
                return response()->json([
                    'message' => 'Class not found or not associated with the user',
                    'messageType' => 'error',
                ], 404);
            }


            foreach ($req->member_ids as $memberId) {
                ScheduleDutyModel::create([
                    'member_id' => $memberId,
                    'day' => $req->day_number,
                    'class_id' => $class->id,
                ]);
            }
            

            return response()->json([
                'message' => 'Schedule set successfully',
                'messageType' => 'success'
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()

            ], 201);
            //throw $th;
        }
    }


    function RemoveSchedule(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  new ClassModel();

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }

        $schedule = ScheduleSubjectModel::where("id", $req->id)->delete();

        return response()->json([
            'message' => 'Schedule delete successfully',
            'messageType' => 'success',
            'schedule' => $schedule,
        ], 201);
    }
    function RemoveDutySchedule(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  new ClassModel();

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }

        $schedule = ScheduleDutyModel::where("id", $req->id)->delete();

        return response()->json([
            'message' => 'Schedule delete successfully',
            'messageType' => 'success',
            'schedule' => $schedule,
        ], 201);
    }

    function GetIdleSubject(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  new ClassModel();

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }
        // Get subject IDs that are already used in the schedule
        $scheduledSubjectIds = ScheduleSubjectModel::where('class_id', $class->id)->pluck('subject_id');

        // Get all subjects for the class that are NOT in the schedule
        $subjects = SubjectModel::where('class_id', $class->id)
            ->get();


        return response()->json([
            'message' => 'Subject get successfully',
            'messageType' => 'success',
            'subjects' => $subjects,
            'subjectsIds' => $scheduledSubjectIds,
        ], 201);
    }
    function GetIdleMember(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  new ClassModel();

        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            $class = ClassModel::where('id', $memberData->class_id)->first();
        }
        // Get subject IDs that are already used in the schedule
        $scheduleDutyIds = ScheduleDutyModel::where('class_id', $class->id)->pluck('member_id');

        // Get all subjects for the class that are NOT in the schedule
        $members = MemberModel::where('class_id', $class->id)
            ->whereNotIn('id', $scheduleDutyIds)->with('user')
            ->get();


        return response()->json([
            'message' => 'Subject get successfully',
            'messageType' => 'success',
            'members' => $members,
        ], 201);
    }
}
