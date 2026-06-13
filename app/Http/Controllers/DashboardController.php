<?php

namespace App\Http\Controllers;

use App\Models\Client\Client;
use App\Models\Vehicles\Vehicle;
use App\Models\Product\Product;
use App\Models\Sales\Sale;
use App\Models\Sales\SaleDetail;
use App\Models\Finance\FinanceRecord;
use App\Models\Finance\FinancialMovement;
use App\Models\Invoice\Invoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Fetch key KPIs and chart metrics for the automotive dashboard.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // 1. Core KPIs
        $totalClients = Client::count();
        $totalVehicles = Vehicle::count();

        // Count products under minimum stock (physical products only)
        $lowStockCount = Product::where('item_type', 1)
            ->whereRaw('stock <= min_stock')
            ->count();

        // Get details of all low stock products to show in the list
        $lowStockProducts = Product::where('item_type', 1)
            ->whereRaw('stock <= min_stock')
            ->orderBy('stock', 'asc')
            ->get(['id', 'description', 'sku', 'stock', 'min_stock']);

        // Date ranges for current month and current year YTD
        $now = Carbon::now('America/Guayaquil');
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();

        // 2. Balance of Current Month
        // Sales (excluding drafts, canceled ones, and quotes)
        $totalSales = (float) Sale::where('status', '!=', 'draft')
            ->where('status', '!=', 'canceled')
            ->where('document_type', '!=', 'quote')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total');

        // Expenses (from FinanceRecords where type = 1)
        $totalExpenses = (float) FinanceRecord::where('type', FinanceRecord::TYPE_EXPENSE)
            ->whereBetween('entry_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyBalance = $totalSales - $totalExpenses;

        // 3. Top 5 Products / Services Sold in Current Month
        $topProducts = SaleDetail::select(
            'product_id',
            'description',
            DB::raw('SUM(quantity) as total_quantity'),
            DB::raw('SUM(total) as total_revenue')
        )
            ->whereHas('sale', function ($q) use ($startOfMonth, $endOfMonth) {
                $q->where('status', '!=', 'draft')
                    ->where('status', '!=', 'canceled')
                    ->where('document_type', '!=', 'quote')
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
            })
            ->groupBy('product_id', 'description')
            ->orderByDesc('total_quantity')
            ->take(5)
            ->get();

        // 3.5 Products vs Services Revenue in Current Month
        $serviceRevenue = (float) SaleDetail::whereHas('sale', function ($q) use ($startOfMonth, $endOfMonth) {
            $q->where('status', '!=', 'draft')
                ->where('status', '!=', 'canceled')
                ->where('document_type', '!=', 'quote')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
        })
            ->where(function ($query) {
                $query->whereNull('product_id')
                    ->orWhereHas('product', function ($q) {
                        $q->where('item_type', 2);
                    });
            })
            ->sum('total');

        $productRevenue = (float) SaleDetail::whereHas('sale', function ($q) use ($startOfMonth, $endOfMonth) {
            $q->where('status', '!=', 'draft')
                ->where('status', '!=', 'canceled')
                ->where('document_type', '!=', 'quote')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
        })
            ->whereHas('product', function ($q) {
                $q->where('item_type', 1);
            })
            ->sum('total');

        // 4. Monthly Sales Trend YTD
        $monthlySalesTrend = Sale::selectRaw('MONTH(created_at) as month, SUM(total) as total')
            ->where('status', '!=', 'draft')
            ->where('status', '!=', 'canceled')
            ->where('document_type', '!=', 'quote')
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 5. YTD Cash Flow (Polymorphic Financial Movements)
        $cashFlowTrend = FinancialMovement::selectRaw('MONTH(entry_date) as month, type, SUM(amount) as total')
            ->whereBetween('entry_date', [$startOfYear, $endOfYear])
            ->whereIn('type', ['income', 'expense'])
            ->groupBy('month', 'type')
            ->get();

        // Helper structure to format 12 months YTD
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre'
        ];

        $salesTrendArray = [];
        $cashFlowArray = [];

        foreach ($months as $num => $name) {
            $salesTrendArray[] = [
                'month_num' => $num,
                'month_name' => $name,
                'total' => 0.00
            ];
            $cashFlowArray[] = [
                'month_num' => $num,
                'month_name' => $name,
                'income' => 0.00,
                'expense' => 0.00
            ];
        }

        // Map sales trend YTD
        foreach ($monthlySalesTrend as $record) {
            $idx = $record->month - 1;
            if (isset($salesTrendArray[$idx])) {
                $salesTrendArray[$idx]['total'] = round((float) $record->total, 2);
            }
        }

        // Map cash flow YTD
        foreach ($cashFlowTrend as $record) {
            $idx = $record->month - 1;
            if (isset($cashFlowArray[$idx])) {
                if ($record->type === 'income') {
                    $cashFlowArray[$idx]['income'] = round((float) $record->total, 2);
                } elseif ($record->type === 'expense') {
                    $cashFlowArray[$idx]['expense'] = round((float) $record->total, 2);
                }
            }
        }

        // 6. Top Clients by YTD Revenue
        $topClients = Sale::select('client_id', DB::raw('SUM(total) as total_sales'))
            ->where('status', '!=', 'draft')
            ->where('status', '!=', 'canceled')
            ->where('document_type', '!=', 'quote')
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->groupBy('client_id')
            ->orderByDesc('total_sales')
            ->take(5)
            ->with('client:id,full_name,name,surname,type_client')
            ->get()
            ->map(function ($item) {
                $clientName = 'Cliente Desconocido';
                if ($item->client) {
                    $clientName = $item->client->full_name ?: trim($item->client->name . ' ' . $item->client->surname);
                }
                return [
                    'name' => $clientName ?: 'Cliente Desconocido',
                    'total' => round((float) $item->total_sales, 2)
                ];
            });

        // 7. Top Suppliers by YTD Purchase Invoices
        $topSuppliers = Invoice::select('supplier_id', DB::raw('SUM(total) as total_purchases'))
            ->whereBetween('created_at', [$startOfYear, $endOfYear])
            ->groupBy('supplier_id')
            ->orderByDesc('total_purchases')
            ->take(5)
            ->with('supplier:id,name')
            ->get()
            ->map(function ($item) {
                $supplierName = 'Proveedor Desconocido';
                if ($item->supplier) {
                    $supplierName = $item->supplier->name;
                }
                return [
                    'name' => $supplierName ?: 'Proveedor Desconocido',
                    'total' => round((float) $item->total_purchases, 2)
                ];
            });

        // 8. Work Orders Report (Rendimiento de Técnicos / OTs)
        // OT Totales grouped by status
        $otTotales = \App\Models\WorkOrder\WorkOrder::select('status', DB::raw('count(*) as count'))
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return ['status' => $item->status, 'count' => $item->count];
            });

        // SLA (Días para cerrar OTs)
        $slas = \App\Models\WorkOrder\WorkOrder::whereIn('status', ['CERRADA_OK', 'FINALIZADA', 'FINALIZADO'])
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get(['created_at', 'updated_at']);
        
        $slaBuckets = [
            '1 día' => 0,
            '2-3 días' => 0,
            '4-7 días' => 0,
            '+8 días' => 0
        ];
        
        foreach ($slas as $sla) {
            $days = $sla->created_at->diffInDays($sla->updated_at);
            if ($days <= 1) {
                $slaBuckets['1 día']++;
            } elseif ($days <= 3) {
                $slaBuckets['2-3 días']++;
            } elseif ($days <= 7) {
                $slaBuckets['4-7 días']++;
            } else {
                $slaBuckets['+8 días']++;
            }
        }

        // Técnicos (Work Orders per technician by status)
        $technicianReportRaw = DB::table('work_order_technicians')
            ->join('work_orders', 'work_order_technicians.work_order_id', '=', 'work_orders.id')
            ->join('employees', 'work_order_technicians.employee_id', '=', 'employees.id')
            ->select('employees.first_name', 'employees.last_name', 'work_orders.status', DB::raw('count(*) as count'))
            ->whereBetween('work_orders.created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'work_orders.status')
            ->get();

        $techniciansData = [];
        foreach ($technicianReportRaw as $row) {
            // First Name only to keep chart labels small, plus initial of surname
            $techName = trim($row->first_name . ' ' . substr($row->last_name, 0, 1) . '.');
            if (!isset($techniciansData[$techName])) {
                $techniciansData[$techName] = [];
            }
            $techniciansData[$techName][$row->status] = $row->count;
        }

        // Satisfacción (Mocked as there's no DB field currently)
        $satisfactionMock = [
            ['status' => 'Muy conforme', 'count' => rand(70, 90)],
            ['status' => 'Conforme', 'count' => rand(10, 20)],
            ['status' => 'Disconforme', 'count' => rand(0, 5)],
        ];

        return response()->json([
            'status' => 200,
            'data' => [
                'kpis' => [
                    'total_clients' => $totalClients,
                    'total_vehicles' => $totalVehicles,
                    'low_stock_count' => $lowStockCount,
                    'low_stock_products' => $lowStockProducts,
                    'monthly_sales' => round($totalSales, 2),
                    'monthly_expenses' => round($totalExpenses, 2),
                    'monthly_balance' => round($monthlyBalance, 2),
                    'work_orders_report' => [
                        'ot_totales' => $otTotales,
                        'sla' => $slaBuckets,
                        'technicians' => $techniciansData,
                        'satisfaction' => $satisfactionMock
                    ]
                ],
                'sales_by_type' => [
                    'products' => round($productRevenue, 2),
                    'services' => round($serviceRevenue, 2)
                ],
                'top_products' => $topProducts,
                'sales_trend' => $salesTrendArray,
                'cash_flow' => $cashFlowArray,
                'top_clients' => $topClients,
                'top_suppliers' => $topSuppliers
            ]
        ]);
    }

    /**
     * Search clients, vehicles, products, and sales from the database.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function search(\Illuminate\Http\Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([
                'status' => 200,
                'results' => []
            ]);
        }

        // 1. Search Clients
        $clients = Client::where('name', 'like', "%$q%")
            ->orWhere('surname', 'like', "%$q%")
            ->orWhere('full_name', 'like', "%$q%")
            ->orWhere('n_document', 'like', "%$q%")
            ->take(5)
            ->get();

        // 2. Search Vehicles
        $vehicles = Vehicle::where('license_plate', 'like', "%$q%")
            ->orWhere('model', 'like', "%$q%")
            ->orWhere('brand', 'like', "%$q%")
            ->take(5)
            ->get();

        // 3. Search Products
        $products = Product::where('description', 'like', "%$q%")
            ->orWhere('sku', 'like', "%$q%")
            ->take(5)
            ->get(['id', 'description', 'sku', 'stock', 'price_sale', 'item_type']);

        // 4. Search Sales
        $sales = Sale::where('document_number', 'like', "%$q%")
            ->orWhereHas('client', function ($query) use ($q) {
                $query->where('full_name', 'like', "%$q%")
                    ->orWhere('n_document', 'like', "%$q%");
            })
            ->with(['client:id,full_name,name,surname'])
            ->take(5)
            ->get(['id', 'document_number', 'client_id', 'total', 'created_at']);

        // Format results
        $results = [];

        foreach ($clients as $client) {
            $name = $client->full_name ?: trim($client->name . ' ' . $client->surname);
            $results[] = [
                'type' => 'Cliente',
                'name' => $name ?: 'Cliente Desconocido',
                'detail' => 'CI/RUC: ' . ($client->n_document ?: 'N/A') . ($client->type_client == 2 ? ' - Empresa' : ' - Persona'),
                'route' => '/clients',
                'raw_data' => $client
            ];
        }

        foreach ($vehicles as $vehicle) {
            $results[] = [
                'type' => 'Vehículo',
                'name' => $vehicle->license_plate . ' [' . ($vehicle->brand ?: 'Genérico') . ' ' . ($vehicle->model ?: '') . ']',
                'detail' => 'Color: ' . ($vehicle->color ?: 'N/A'),
                'route' => '/vehicles',
                'raw_data' => $vehicle
            ];
        }

        foreach ($products as $product) {
            $results[] = [
                'type' => $product->item_type == 2 ? 'Servicio' : 'Producto',
                'name' => $product->description,
                'detail' => 'SKU: ' . ($product->sku ?: 'N/A') . ' | Precio: $' . number_format($product->price_sale, 2),
                'route' => $product->item_type == 2 ? '/work-orders/add' : '/product/list'
            ];
        }

        foreach ($sales as $sale) {
            $clientName = 'Desconocido';
            if ($sale->client) {
                $clientName = $sale->client->full_name ?: trim($sale->client->name . ' ' . $sale->client->surname);
            }
            $results[] = [
                'type' => 'Venta',
                'name' => 'Venta #' . $sale->document_number,
                'detail' => 'Cliente: ' . $clientName . ' | Total: $' . number_format($sale->total, 2),
                'route' => '/sales/list'
            ];
        }

        return response()->json([
            'status' => 200,
            'results' => $results
        ]);
    }
}

