<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinanceRecordRequest;
use App\Http\Resources\FinanceRecordResource;
use App\Models\FinanceRecord;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Carbon\Carbon;

class FinanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FinanceRecord::orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by account if provided
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filter by date range if provided
        if ($request->has('start_date')) {
            $query->whereDate('entry_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('entry_date', '<=', $request->end_date);
        }

        $records = $query->get();

        return FinanceRecordResource::collection($records);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFinanceRecordRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Set entry_date to current date in Ecuador timezone if not provided
        if (empty($data['entry_date'])) {
            $data['entry_date'] = now('America/Guayaquil')->toDateString();
        }

        // Set user_id from authenticated user
        $data['user_id'] = Auth::id() ?? 1; // Default to user 1 if not authenticated

        $record = FinanceRecord::create($data);

        // Update account balance
        $account = Account::find($data['account_id']);
        if ($account) {
            $account->updateBalance($data['amount'], $data['type']);
        }

        return response()->json([
            'message' => 'Finance record created successfully',
            'data' => new FinanceRecordResource($record)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FinanceRecord $financeRecord): FinanceRecordResource
    {
        $financeRecord->load('user');
        return new FinanceRecordResource($financeRecord);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreFinanceRecordRequest $request, FinanceRecord $financeRecord): JsonResponse
    {
        $data = $request->validated();

        $financeRecord->update($data);
        $financeRecord->load('user');

        return response()->json([
            'message' => 'Finance record updated successfully',
            'data' => new FinanceRecordResource($financeRecord)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinanceRecord $financeRecord): JsonResponse
    {
        $financeRecord->delete();

        return response()->json([
            'message' => 'Finance record deleted successfully'
        ]);
    }

    /**
     * Get daily summary grouped by entry_date.
     */
    public function dailySummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now('America/Guayaquil')->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now('America/Guayaquil')->toDateString());

        $summary = FinanceRecord::selectRaw('
                entry_date,
                SUM(CASE WHEN type = 0 THEN amount ELSE 0 END) as total_incomes,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) as total_expenses,
                (SUM(CASE WHEN type = 0 THEN amount ELSE 0 END) - SUM(CASE WHEN type = 1 THEN amount ELSE 0 END)) as net_balance
            ')
            ->whereDate('entry_date', '>=', $startDate)
            ->whereDate('entry_date', '<=', $endDate)
            ->groupBy('entry_date')
            ->orderBy('entry_date', 'desc')
            ->get();

        // Calculate totals
        $totals = [
            'total_incomes' => $summary->sum('total_incomes'),
            'total_expenses' => $summary->sum('total_expenses'),
            'total_balance' => $summary->sum('net_balance'),
        ];

        return response()->json([
            'data' => $summary,
            'totals' => $totals,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    /**
     * Get statistics for the current month.
     */
    public function monthlyStats(): JsonResponse
    {
        $currentMonth = now('America/Guayaquil')->startOfMonth();
        $endOfMonth = now('America/Guayaquil')->endOfMonth();

        $stats = FinanceRecord::selectRaw('
                SUM(CASE WHEN type = 0 THEN amount ELSE 0 END) as total_incomes,
                SUM(CASE WHEN type = 1 THEN amount ELSE 0 END) as total_expenses,
                COUNT(CASE WHEN type = 0 THEN 1 END) as income_count,
                COUNT(CASE WHEN type = 1 THEN 1 END) as expense_count
            ')
            ->whereDate('entry_date', '>=', $currentMonth)
            ->whereDate('entry_date', '<=', $endOfMonth)
            ->first();

        return response()->json([
            'data' => $stats,
            'period' => [
                'month' => $currentMonth->format('F Y'),
                'start_date' => $currentMonth->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
            ]
        ]);
    }
}
