<?php

namespace App\Http\Controllers;

use App\Models\CashLogModel;
use App\Models\CashModel;
use App\Models\ClassModel;
use App\Models\MemberModel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class MemberController extends Controller
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

    public function addMember(Request $req)
    {
        // Ensure the authenticated user exists
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  $this->getUserClass($user, ['Leader']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }


        // Validate incoming request data
        $validated = $req->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|string|in:Secretary,Treasurer,Member,Teacher',
            'password' => 'required|string|min:6',
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
        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']), // Hash password for security
            'avatar' => $avatarPath, // Store the path to the uploaded avatar
        ]);

        // Create member record
        MemberModel::create([
            'user_id' => $newUser->id,
            'class_id' => $class->id,
            'access_code' => $validated['password']
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'messageType' => 'success',
            'user' => [
                'id' => $newUser->id,
                'name' => $newUser->name,
                'email' => $newUser->email,
                'role' => $newUser->role,
                'avatar' => $avatarPath ? asset('storage/' . $avatarPath) : null,
            ],
        ], 201);
    }

    public function editMember(Request $req)
    {
        try {
            //code...
            // Ensure the authenticated user exists (optional)
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $class =  $this->getUserClass($user, ['Leader']);
            if ($class instanceof \Illuminate\Http\JsonResponse) {
                return $class; // 🔁 Immediately return the response, breaking the flow
            }


            $id = $req['id'];

            // Find the member's user record
            $member = MemberModel::where('id', $id)->first();
            if (!$member) {
                return response()->json(['message' => 'Member not found'], 404);
            }

            $memberUser = User::where('id', $member->user_id)->first();
            if (!$memberUser) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Validate incoming request data
            $validated = $req->validate([
                'name' => 'sometimes|string|max:255',
                'email' => "sometimes|email|unique:users,email,$memberUser->id",
                'role' => 'sometimes|string|in:Secretary,Treasurer,Member,Teacher',
                'access_code' => 'sometimes|string|min:6',
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:3024', // Added avatar validation
            ]);

            // Handle avatar upload if present
            $avatarPath = $memberUser->avatar; // Keep existing avatar by default
            if ($req->hasFile('avatar') && $req->file('avatar')->isValid()) {
                // Delete old avatar if exists
                if ($memberUser->avatar && Storage::disk('public')->exists($memberUser->avatar)) {
                    Storage::disk('public')->delete($memberUser->avatar);
                }

                // Upload new avatar
                $avatar = $req->file('avatar');
                $filename = time() . '_' . $avatar->getClientOriginalName();
                $avatarPath = $avatar->storeAs('avatars', $filename, 'public');
            }

            // Update user details
            $memberUser->update(array_filter([
                'name' => $validated['name'] ?? $memberUser->name,
                'email' => $validated['email'] ?? $memberUser->email,
                'role' => $validated['role'] ?? $memberUser->role,
                'password' => isset($validated['access_code']) ? Hash::make($validated['access_code']) : null,
                'avatar' => $avatarPath, // Add updated avatar path
            ], function ($value) {
                return $value !== null;
            }));

            // Update member access_code if provided
            if (isset($validated['access_code'])) {
                $member->update(['access_code' => $validated['access_code']]);
            }

            return response()->json([
                'message' => 'Member updated successfully',
                'messageType' => 'success',
                'user' => $memberUser,
                'member' => $member,
                'avatar' => $avatarPath ? asset('storage/' . $avatarPath) : null, // Include avatar URL in response
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'messageType' => 'success',
            ], 200);
            //throw $th;
        }
    }


    public function getMember(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  $this->getUserClass($user, ['Teacher','Leader', 'Treasurer', 'Secretary', 'Member']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }

        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class_id = $class->id;

        $search = $req->input('search');
        $role = $req->input('role');
        $perPage = $req->input('per_page', 10);
        $page = $req->input('page', 1);

        $membersQuery = MemberModel::where('class_id', $class_id)
            ->with('user');

        if ($search) {
            $membersQuery->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($role) {
            $membersQuery->whereHas('user', function ($query) use ($role) {
                $query->where('role', $role);
            });
        }

        $members = $membersQuery->paginate($perPage, ['*'], 'page', $page);

        $year = $req->input('year', now()->year);
        $month = $req->input('month', now()->month);

        $start_date = Carbon::create($year, $month, 1);
        $weeks = [
            'minggu_1' => $start_date->copy()->addDays(0)->format('Y-m-d'),
            'minggu_2' => $start_date->copy()->addDays(7)->format('Y-m-d'),
            'minggu_3' => $start_date->copy()->addDays(14)->format('Y-m-d'),
            'minggu_4' => $start_date->copy()->addDays(21)->format('Y-m-d'),
        ];

        $members->getCollection()->transform(function ($siswa) use ($weeks, $class_id) {
            $pembayaran = [];
            $total_nominal = 0;

            foreach ($weeks as $minggu => $tanggal) {
                $kas = CashModel::where('member_id', $siswa->id)
                    ->where('class_id', $class_id)
                    ->whereDate('tanggal', $tanggal)
                    ->first();

                if ($kas) {
                    $pembayaran[$minggu] = [
                        'status' => $kas->status,
                        'nominal' => $kas->nominal,
                        'tanggal' => $kas->tanggal,
                    ];
                    $total_nominal += $kas->nominal;
                } else {
                    $pembayaran[$minggu] = [
                        'status' => 'Belum Bayar',
                        'nominal' => 0,
                    ];
                }
            }

            return [
                'id' => $siswa->id,
                'user_id' => $siswa->user_id,
                'class_id' => $siswa->class_id,
                'pembayaran' => $pembayaran,
                'total_pembayaran' => $total_nominal,
                'access_code' => $siswa->access_code,
                'created_at' => $siswa->created_at,
                'updated_at' => $siswa->updated_at,
                'user' => $siswa->user,
            ];
        });

        return response()->json([
            'message' => 'User get successfully',
            'messageType' => 'success',
            'data' => [
                'class' => [
                    'id' => $class->id,
                    'name' => $class->name,
                ],
                'members' => $members,
            ]
        ], 200);
    }



    public function deleteMember(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $class =  $this->getUserClass($user, ['Leader']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }


        // Validate request
        $validatedData = $req->validate([
            'member_id' => 'required|exists:member_models,id',
            'user_id' => 'required|exists:users,id',
        ]);

        // Fetch the member
        $member = MemberModel::find($validatedData['member_id']);
        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        // Fetch the user to be deleted
        $userToDelete = User::find($validatedData['user_id']);
        if (!$userToDelete) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Delete avatar file if it exists
        if ($userToDelete->avatar && Storage::disk('public')->exists($userToDelete->avatar)) {
            Storage::disk('public')->delete($userToDelete->avatar);
        }

        // Delete related CashLogModel records first
        $cashModelEntries = CashModel::where('member_id', $member->id)->get();
        foreach ($cashModelEntries as $cash) {
            CashLogModel::where('cash_id', $cash->id)->delete();
        }

        // Delete related CashModel records
        CashModel::where('member_id', $member->id)->delete();

        // Delete the member and user
        $memberDeleted = $member->delete();
        $userDeleted = $userToDelete->delete();

        return response()->json([
            'message' => 'Member and User deleted successfully',
            'messageType' => 'success',
            'data' => [
                'memberDeleted' => $memberDeleted,
                'userDeleted' => $userDeleted
            ]
        ], 200);
    }
}
