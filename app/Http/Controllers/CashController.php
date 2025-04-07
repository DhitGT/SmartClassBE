<?php

namespace App\Http\Controllers;

use App\Models\CashLogModel;
use App\Models\CashModel;
use App\Models\ClassModel;
use App\Models\MemberModel;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashController extends Controller
{
    //
    public function addTransaction(Request $req)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get class based on role
        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            if (!$memberData) {
                return response()->json(['message' => 'Class not found'], 404);
            }
            $class = ClassModel::where('id', $memberData->class_id)->first();
        } else {
            return response()->json(['message' => 'User does not have permission'], 403);
        }

        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class_id = $class->id;

        // Validate request data
        $validatedData = $req->validate([
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'member_id' => 'required_if:type,income',
            'year' => 'required_if:type,income|integer|min:2000|max:' . Carbon::now()->year + 1,
            'month' => 'required_if:type,income|integer|min:1|max:12',
            'week' => 'required_if:type,income|in:minggu_1,minggu_2,minggu_3,minggu_4',
        ]);

        $year = $req->year ?? now()->year;
        $month = $req->month ?? now()->month;
        if ($validatedData['type'] == 'income') {

            // Get the date for the selected week
            $weeks = [
                'minggu_1' => Carbon::createFromDate($year, $month, 1)->startOfMonth(),
                'minggu_2' => Carbon::createFromDate($year, $month, 1)->startOfMonth()->addDays(7),
                'minggu_3' => Carbon::createFromDate($year, $month, 1)->startOfMonth()->addDays(14),
                'minggu_4' => Carbon::createFromDate($year, $month, 1)->startOfMonth()->addDays(21),
            ];
            $transaction_date = $weeks[$validatedData['week']]->format('Y-m-d');

            // Find member
            $member = MemberModel::where("user_id", $validatedData['member_id'])->with('user')->first();
            $member_id = $member ? $member->id : null;

            // Check if the cash entry already exists
            $cash = CashModel::where('member_id', $member_id)
                ->where('class_id', $class_id)
                ->where('tahun', $year)
                ->where('bulan', $month)
                ->where('minggu', $validatedData['week'])
                ->first();

            if ($cash) {
                // Update existing entry
                $cash->nominal = $validatedData['amount'];
                $cash->status = 'Sudah Bayar';
                $cash->tanggal = $transaction_date;
                $cash->save();
            } else {
                // Create new entry
                $cash = new CashModel();
                $cash->id = Str::uuid();
                $cash->member_id = $member_id;
                $cash->class_id = $class_id;
                $cash->tahun = $year;
                $cash->bulan = $month;
                $cash->minggu = $validatedData['week'];
                $cash->status = 'Sudah Bayar';
                $cash->nominal = $validatedData['amount'];
                $cash->tanggal = $transaction_date;
                $cash->save();
            }

            // Save income transaction log
            $log = new CashLogModel();
            $log->id = Str::uuid();
            $log->cash_id = $cash->id;
            $log->class_id = $class_id;
            $log->bulan = $month;
            $log->tahun = $year;
            $log->type = 'income';
            $log->amount = $validatedData['amount'];
            $log->description = 'Cash payment from ' . $member->user->name;
            $log->save();

            return response()->json([
                'message' => 'Income transaction successfully added',
                'data' => $log
            ], 201);
        } else if ($validatedData['type'] == 'expense') {
            $total_pemasukan = CashModel::where('class_id', $class_id)
                ->where('status', 'Sudah Bayar')
                ->sum('nominal');

            // Total Pengeluaran (Expenses)
            $total_pengeluaran = CashLogModel::where('class_id', $class_id)
                ->where('type', 'expense')
                ->sum('amount');

            // Saldo Kas (Total Cash)
            $total_kas = $total_pemasukan - $total_pengeluaran;

            if($total_kas <= $validatedData['amount']){
                return response()->json([
                    'message' => 'Expense transaction failed, insuficient balance',
                    // 'data' => $log
                ], 201);
            }else{
                $log = new CashLogModel();
                $log->id = Str::uuid();
                $log->cash_id = null; // No linked cash entry for expenses
                $log->class_id = $class_id;
                $log->type = 'expense';
                $log->bulan = $month;
                $log->tahun = $year;
                $log->amount = $validatedData['amount'];
                $log->description = $validatedData['description'] ?? 'Cash Expense';
                $log->save();
    
                return response()->json([
                    'message' => 'Expense transaction successfully added',
                    'data' => $log
                ], 201);
            }

            // Save expense transaction log
        }

        return response()->json(['message' => 'Invalid transaction type'], 400);
    }


    public function getClassCashSummary(Request $req)
    {
        $user = Auth::user();
        $class = ClassModel::where('leader_id', $user->id)->first();

        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class_id = $class->id;
        $year = $req->year ?? now()->year;
        $month = $req->month ?? now()->month;
        $currentWeek = now()->weekOfMonth;

        if($req->month < now()->month){
            $currentWeek = 4;
        }


        // Previous Month Calculation
        $previousMonth = $month == 1 ? 12 : $month - 1;
        $previousYear = $month == 1 ? $year - 1 : $year;

        // Total Revenue (Income) - Now filtered by the selected month & year
        $totalRevenue = CashModel::where('class_id', $class_id)
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->where('status', 'Sudah Bayar')
            ->sum('nominal');

        // Get all members in the class
        $allMembers = MemberModel::where('class_id', $class_id)->pluck('id');

        // Get members who have completed payments
        $completedMembers = CashModel::where('class_id', $class_id)
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->where('status', 'Sudah Bayar')
            ->where('minggu', '<=', $currentWeek)
            ->groupBy('member_id')
            ->havingRaw('COUNT(DISTINCT minggu) = ?', [$currentWeek])
            ->pluck('member_id');

        $totalCompleted = CashModel::whereIn('member_id', $completedMembers)
            ->where('class_id', $class_id)
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->sum('nominal');

        // Count Members Who Completed Payment
        $completedMemberCount = $completedMembers->count();

        // Find Members Who Haven't Completed Payment
        $membersWhoPaidAtLeastOnce = CashModel::where('class_id', $class_id)
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->pluck('member_id')
            ->unique();

        $pendingMembers = $allMembers->diff($completedMembers); // Members who are not fully paid
        $neverPaidMembers = $allMembers->diff($membersWhoPaidAtLeastOnce); // Members who never paid at all

        // Total Pending Payment (Including Members Who Never Paid)
        $totalPending = CashModel::whereIn('member_id', $pendingMembers)
            ->where('class_id', $class_id)
            ->where('tahun', $year)
            ->where('bulan', $month)
            ->sum('nominal');

        // Estimate amount owed by members who never paid
        $weeklyFee = 5000; // Example: Each week costs 5000
        $neverPaidTotal = $neverPaidMembers->count() * ($weeklyFee * $currentWeek);

        // Final total pending amount including unpaid members
        $totalPending += $neverPaidTotal;

        // Count Members Who Haven't Completed Payment
        $pendingMemberCount = $pendingMembers->count();

        // Total Pemasukan (Income from payments)
        $total_pemasukan = CashModel::where('class_id', $class_id)
            ->where('status', 'Sudah Bayar')
            ->sum('nominal');

        // Total Pengeluaran (Expenses)
        $total_pengeluaran = CashLogModel::where('class_id', $class_id)
            ->where('type', 'expense')
            ->sum('amount');

        // Saldo Kas (Total Cash)
        $total_kas = $total_pemasukan - $total_pengeluaran;

        // ** Calculate Last Month's Completed Payments **
        $totalCompletedLastMonth = CashModel::where('class_id', $class_id)
            ->where('tahun', $previousYear)
            ->where('bulan', $previousMonth)
            ->sum('nominal');

        $totalRevenueLastMonth = CashModel::where('class_id', $class_id)
            ->where('tahun', $previousYear)
            ->where('bulan', $previousMonth)
            ->where('status', 'Sudah Bayar')
            ->sum('nominal');

        // ** Corrected Percentage Change Calculation **
        if ($totalRevenueLastMonth > 0) {
            $percentageChange = (($totalRevenue - $totalRevenueLastMonth) / $totalRevenueLastMonth) * 100;
        } else {
            $percentageChange = $totalRevenue > 0 ? 100 : 0; // If last month was 0, assume 100% increase only if there's income this month
        }
        $percentage_arrow = $percentageChange > 0 ? '↑' : ($percentageChange < 0 ? '↓' : '-');

        return response()->json([
            'year' => $year,
            'month' => $month,
            'class_id' => $class_id,
            'total_cash' => $total_kas,
            'total_revenue' => $totalRevenue,
            'total_completed_payment' => $totalCompleted,
            'completed_members_count' => $completedMemberCount,
            'total_pending_payment' => $totalPending,
            'pending_members_count' => $pendingMemberCount,
            'current_week' => $currentWeek,
            'percentage_change' => round($percentageChange, 2) . '%',
            'percentage_arrow' => $percentage_arrow
        ]);
    }


    public function editTransaction(Request $req)
    {
        try {
            //code...
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Get class based on role
            if ($user->role == 'Leader') {
                $class = ClassModel::where('leader_id', $user->id)->first();
            } else if ($user->role == 'Secretary') {
                $memberData = MemberModel::where('user_id', $user->id)->first();
                if (!$memberData) {
                    return response()->json(['message' => 'Class not found'], 404);
                }
                $class = ClassModel::where('id', $memberData->class_id)->first();
            } else {
                return response()->json(['message' => 'User does not have permission'], 403);
            }

            if (!$class) {
                return response()->json(['message' => 'Class not found'], 404);
            }

            $class_id = $class->id;

            // Validate request data
            $validatedData = $req->validate([
                'id' => 'required|exists:member_models,id', // Ensure the member exists
                'year' => 'required|integer|min:2000|max:' . Carbon::now()->year + 1,
                'month' => 'required|integer|min:1|max:12',
                'minggu_1' => 'nullable|numeric|min:0',
                'minggu_2' => 'nullable|numeric|min:0',
                'minggu_3' => 'nullable|numeric|min:0',
                'minggu_4' => 'nullable|numeric|min:0',
            ]);

            $member = MemberModel::where("id", $validatedData['id'])->with('user')->first();
            if (!$member) {
                return response()->json(['message' => 'Member not found'], 404);
            }

            $member_id = $member->id;
            $year = $validatedData['year'];
            $month = $validatedData['month'];

            // Define weeks and dates
            $weeks = [
                'minggu_1' => Carbon::createFromDate($year, $month, 1)->startOfMonth(),
                'minggu_2' => Carbon::createFromDate($year, $month, 1)->startOfMonth()->addDays(7),
                'minggu_3' => Carbon::createFromDate($year, $month, 1)->startOfMonth()->addDays(14),
                'minggu_4' => Carbon::createFromDate($year, $month, 1)->startOfMonth()->addDays(21),
            ];

            $updatedTransactions = [];

            foreach (['minggu_1', 'minggu_2', 'minggu_3', 'minggu_4'] as $week) {
                if ($req->has($week) && $validatedData[$week] != 0) {
                    $amount = $validatedData[$week];
                    $transaction_date = $weeks[$week]->format('Y-m-d');

                    // Check if cash record exists
                    $cash = CashModel::where('member_id', $member_id)
                        ->where('class_id', $class_id)
                        ->where('tahun', $year)
                        ->where('bulan', $month)
                        ->where('minggu', $week)
                        ->first();

                    if ($cash) {
                        // Update existing entry
                        $cash->nominal = $amount;
                        if ($amount == '0') {
                            $cash->status = 'Belum Bayar';
                        } else {
                            $cash->status = 'Sudah Bayar';
                        }
                        $cash->tanggal = $transaction_date;
                        $cash->save();
                    } else {
                        // Create new entry
                        $cash = new CashModel();
                        $cash->id = Str::uuid();
                        $cash->member_id = $member_id;
                        $cash->class_id = $class_id;
                        $cash->tahun = $year;
                        $cash->bulan = $month;
                        $cash->minggu = $week;
                        if ($amount == '0') {
                            $cash->status = 'Belum Bayar';
                        } else {
                            $cash->status = 'Sudah Bayar';
                        }
                        $cash->nominal = $amount;
                        $cash->tanggal = $transaction_date;
                        $cash->save();
                    }

                    // Save transaction log
                    $log = new CashLogModel();
                    $log->id = Str::uuid();
                    $log->cash_id = $cash->id;
                    $log->class_id = $class_id;
                    $log->bulan = $month;
                    $log->tahun = $year;
                    $log->type = 'income';
                    $log->amount = $amount;
                    $log->description = "Updated kas payment for $week from " . $member->user->name;
                    $log->save();

                    $updatedTransactions[] = $log;
                }
            }

            return response()->json([
                'message' => 'Transactions successfully updated',
                'data' => $updatedTransactions
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 200);
            //throw $th;
        }
    }

    public function getCashLog(Request $req)
    {

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get class based on role
        if ($user->role == 'Leader') {
            $class = ClassModel::where('leader_id', $user->id)->first();
        } else if ($user->role == 'Secretary') {
            $memberData = MemberModel::where('user_id', $user->id)->first();
            if (!$memberData) {
                return response()->json(['message' => 'Class not found'], 404);
            }
            $class = ClassModel::where('id', $memberData->class_id)->first();
        } else {
            return response()->json(['message' => 'User does not have permission'], 403);
        }

        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class_id = $class->id;
        $year = $req->year ?? now()->year;
        $month = $req->month ?? now()->month;

        $cashLog = CashLogModel::where('class_id', $class_id)->where('tahun', $year)->where('bulan', $month)->with('cash')->orderBy('created_at', 'desc')->get();



        return response()->json([
            'data' => $cashLog
        ]);
    }

    public function listPembayaranPerBulan(Request $req)
    {
        $user = Auth::user();
        $class = ClassModel::where('leader_id', $user->id)->first();

        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class_id = $class->id;
        $year = $req->year ?? now()->year;
        $month = $req->month ?? now()->month;

        // Ambil semua siswa dalam kelas ini
        $siswa_kelas = MemberModel::where('class_id', $class_id)->with('user')->get();

        // Tentukan tanggal setiap minggu dalam bulan ini
        $start_date = Carbon::createFromDate($year, $month, 1);
        $weeks = [
            'minggu_1' => $start_date->copy()->startOfMonth()->addDays(0)->format('Y-m-d'),
            'minggu_2' => $start_date->copy()->startOfMonth()->addDays(7)->format('Y-m-d'),
            'minggu_3' => $start_date->copy()->startOfMonth()->addDays(14)->format('Y-m-d'),
            'minggu_4' => $start_date->copy()->startOfMonth()->addDays(21)->format('Y-m-d'),
        ];

        $result = [];

        foreach ($siswa_kelas as $siswa) {
            $pembayaran = [];
            $total_nominal = 0; // Inisialisasi total pembayaran

            foreach ($weeks as $minggu => $tanggal) {
                // Cek apakah siswa sudah bayar di minggu tersebut
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
                    $total_nominal += $kas->nominal; // Tambahkan ke total pembayaran
                } else {
                    // Jika tidak ada pembayaran, berarti belum bayar
                    $pembayaran[$minggu] = [
                        'status' => 'Belum Bayar',
                        'nominal' => 0,
                    ];
                }
            }

            // Simpan data pembayaran per siswa dengan total
            $result[$siswa->id] = [
                'id' => $siswa->id,
                'avatar' => $siswa->user->avatar ?? '',
                'nama' => $siswa->user->name ?? 'Nama Tidak Ditemukan',
                'pembayaran' => $pembayaran,
                'total_pembayaran' => $total_nominal, // Tambahkan total pembayaran
            ];
        }

        return response()->json([
            'class_id' => $class_id,
            'year' => $year,
            'month' => $start_date->format('F'),
            'data' => $result
        ]);
    }
}
