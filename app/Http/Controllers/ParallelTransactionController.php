<?php

namespace App\Http\Controllers;

use App\Models\ParallelTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class ParallelTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ParallelTransaction::query();

        // Filter by keyword search
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('unit', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhere('unit_cost', 'like', "%{$search}%");
            });
        }

        // Filter by type (income / expense)
        if ($request->filled('type') && $request->get('type') !== 'ALL') {
            $query->where('type', strtolower($request->get('type')));
        }

        // Filter by account (EFECTIVO / TRANSFERENCIA)
        if ($request->filled('account') && $request->get('account') !== 'ALL') {
            $query->where('account', strtoupper($request->get('account')));
        }

        // Return sorted by date desc, then id desc
        $transactions = $query->orderBy('date', 'desc')
                              ->orderBy('id', 'desc')
                              ->get();

        return response()->json($transactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:income,expense',
            'description' => 'required|string|max:500',
            'quantity' => 'nullable|required_if:type,income|integer|min:1',
            'unit_cost' => 'nullable|required_if:type,income|numeric|min:0.01',
            'cost' => 'nullable|required_if:type,expense|numeric|min:0.01',
            'unit' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'account' => 'required|string|in:EFECTIVO,TRANSFERENCIA',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = auth()->user()->id;

        // Auto capitalization and spacing trim
        $data['description'] = strtoupper(trim($data['description']));
        if (isset($data['unit'])) {
            $data['unit'] = strtoupper(trim($data['unit']));
        }

        // Calculate amount based on transaction type
        if ($data['type'] === 'income') {
            $data['amount'] = floatval($data['quantity']) * floatval($data['unit_cost']);
            $data['cost'] = null;
            $data['unit'] = null;
        } else {
            $data['amount'] = floatval($data['cost']);
            $data['quantity'] = $data['quantity'] ?? null;
            $data['unit_cost'] = $data['unit_cost'] ?? null;
        }

        $transaction = ParallelTransaction::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Registro guardado con éxito.',
            'data' => $transaction,
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $transaction = ParallelTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:income,expense',
            'description' => 'required|string|max:500',
            'quantity' => 'nullable|required_if:type,income|integer|min:1',
            'unit_cost' => 'nullable|required_if:type,income|numeric|min:0.01',
            'cost' => 'nullable|required_if:type,expense|numeric|min:0.01',
            'unit' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'account' => 'required|string|in:EFECTIVO,TRANSFERENCIA',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada no válidos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        
        // Auto capitalization and spacing trim
        $data['description'] = strtoupper(trim($data['description']));
        if (isset($data['unit'])) {
            $data['unit'] = strtoupper(trim($data['unit']));
        }

        // Calculate amount based on transaction type
        if ($data['type'] === 'income') {
            $data['amount'] = floatval($data['quantity']) * floatval($data['unit_cost']);
            $data['cost'] = null;
            $data['unit'] = null;
        } else {
            $data['amount'] = floatval($data['cost']);
            $data['quantity'] = $data['quantity'] ?? null;
            $data['unit_cost'] = $data['unit_cost'] ?? null;
        }

        $transaction->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado con éxito.',
            'data' => $transaction,
        ]);
    }

    /**
     * Generate PDF report for parallel transactions.
     */
    public function generatePDF(Request $request)
    {
        $query = ParallelTransaction::where('user_id', auth()->user()->id);

        // 1. Resolve date range
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $rangeType = $request->get('range', 'all');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$startDate, $endDate]);
            $rangeLabel = "PERÍODO: DEL " . date('d/m/Y', strtotime($startDate)) . " AL " . date('d/m/Y', strtotime($endDate));
        } else {
            switch ($rangeType) {
                case 'today':
                    $today = date('Y-m-d');
                    $query->where('date', $today);
                    $rangeLabel = "PERÍODO: HOY " . date('d/m/Y', strtotime($today));
                    break;
                case 'week':
                    $monday = date('Y-m-d', strtotime('monday this week'));
                    $sunday = date('Y-m-d', strtotime('sunday this week'));
                    $query->whereBetween('date', [$monday, $sunday]);
                    $rangeLabel = "PERÍODO: SEMANA DEL " . date('d/m/Y', strtotime($monday)) . " AL " . date('d/m/Y', strtotime($sunday));
                    break;
                case 'month':
                    $firstDay = date('Y-m-01');
                    $lastDay = date('Y-m-t');
                    $query->whereBetween('date', [$firstDay, $lastDay]);
                    
                    // Spanish month names mapping
                    $months = [
                        1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
                        5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
                        9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE'
                    ];
                    $monthNum = intval(date('n'));
                    $rangeLabel = "PERÍODO: MES DE " . $months[$monthNum] . " " . date('Y');
                    break;
                default:
                    $rangeLabel = "PERÍODO: TODOS LOS MOVIMIENTOS REGISTRADOS";
                    break;
            }
        }

        // Apply account filter if provided
        if ($request->filled('account') && $request->get('account') !== 'ALL') {
            $query->where('account', strtoupper($request->get('account')));
            $rangeLabel .= " | CUENTA: " . strtoupper($request->get('account'));
        }

        $transactions = $query->orderBy('date', 'asc')
                              ->orderBy('id', 'asc')
                              ->get();

        // Calculations
        $totalIncomes = 0.00;
        $totalExpenses = 0.00;
        foreach ($transactions as $t) {
            if ($t->type === 'income') {
                $totalIncomes += floatval($t->amount);
            } else {
                $totalExpenses += floatval($t->amount);
            }
        }
        $balance = $totalIncomes - $totalExpenses;

        $pdf = Pdf::loadView('negocio-paralelo.pdf', compact('transactions', 'totalIncomes', 'totalExpenses', 'balance', 'rangeLabel'));

        return $pdf->stream('reporte_negocio_paralelo_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $transaction = ParallelTransaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado.',
            ], 404);
        }

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado con éxito.',
        ]);
    }
}
