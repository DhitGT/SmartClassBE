<?php

namespace App\Http\Controllers;

use App\Models\CashLogModel;
use App\Models\ClassModel;
use App\Models\TeacherModel;
use App\Models\StudentModel;
use App\Models\SubjectModel;
use App\Models\CashModel;
use App\Models\MemberModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function getUserClass($user,$grantRole){
        $class =  new ClassModel();

        if (!in_array($user->role, $grantRole)){
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

    public function getCountData(Request $req)
    {
        $user = Auth::user();
        $class =  $this->getUserClass($user, ['Leader','Treasurer','Member','Secretary']);
        if ($class instanceof \Illuminate\Http\JsonResponse) {
            return $class; // 🔁 Immediately return the response, breaking the flow
        }


        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }


        $total_pemasukan = CashModel::where('class_id', $class->id)
            ->where('status', 'Sudah Bayar')
            ->sum('nominal');

        // Total pengeluaran
        $total_pengeluaran = CashLogModel::where('class_id', $class->id)->where('type','expense')->sum('amount');

        // Saldo kas
        $total_kas = $total_pemasukan - $total_pengeluaran;

        $data = [
            'teachers' => TeacherModel::where('class_id', $class->id)->count(),
            'students' => MemberModel::where('class_id', $class->id)->count(),
            'subjects' => SubjectModel::where('class_id', $class->id)->count(),
            // 'cash' => CashModel::where('class_id', $class->id)->value('total'),
            'cash' => $total_kas,
        ];

        return response()->json(['data' => $data]);
    }
}
