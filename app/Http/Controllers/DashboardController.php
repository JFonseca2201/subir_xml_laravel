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

        // 7. Top Proveedores a los que más se les ha comprado (Historial de Compras y Facturas)
        $allPurchasesTotal = (float) Invoice::sum('total');
        $topSuppliers = Invoice::select(
            'supplier_id',
            DB::raw('COUNT(id) as total_invoices'),
            DB::raw('SUM(total) as total_purchases'),
            DB::raw('MAX(issue_date) as last_purchase_date')
        )
            ->whereNotNull('supplier_id')
            ->groupBy('supplier_id')
            ->orderByDesc('total_purchases')
            ->take(6)
            ->with('supplier:id,name,ruc,phone,email')
            ->get()
            ->map(function ($item) use ($allPurchasesTotal) {
                $supplierName = 'Proveedor General';
                $ruc = '';
                if ($item->supplier) {
                    $supplierName = $item->supplier->name;
                    $ruc = $item->supplier->ruc ?: ($item->supplier->tax_id ?: '');
                }
                $totalSpent = round((float) $item->total_purchases, 2);
                $percentage = $allPurchasesTotal > 0 ? round(($totalSpent / $allPurchasesTotal) * 100, 1) : 0;

                return [
                    'id' => $item->supplier_id,
                    'name' => $supplierName,
                    'ruc' => $ruc,
                    'invoices_count' => (int) $item->total_invoices,
                    'total' => $totalSpent,
                    'percentage' => $percentage,
                    'last_purchase' => $item->last_purchase_date ? (is_string($item->last_purchase_date) ? substr($item->last_purchase_date, 0, 10) : $item->last_purchase_date->format('Y-m-d')) : null,
                ];
            });

        // 7.5. Productos Más Comprados a Proveedores (Basado en ítems de compras e inventario)
        $topPurchasedQuery = DB::table('invoice_items')
            ->select(
                'description',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total) as total_spent'),
                DB::raw('AVG(unit_price) as avg_price')
            )
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->groupBy('description')
            ->orderByDesc('total_quantity')
            ->take(5)
            ->get();

        $allPurchasedQty = (float) DB::table('invoice_items')->sum('quantity');
        $topQtySum = (float) $topPurchasedQuery->sum('total_quantity');
        $otherQty = max(0.0, round($allPurchasedQty - $topQtySum, 2));

        $topPurchasedProducts = $topPurchasedQuery->map(function ($item) use ($allPurchasedQty) {
            $qty = round((float) $item->total_quantity, 2);
            $spent = round((float) $item->total_spent, 2);
            $percent = $allPurchasedQty > 0 ? round(($qty / $allPurchasedQty) * 100, 1) : 0;

            return [
                'description' => $item->description,
                'total_quantity' => $qty,
                'total_spent' => $spent,
                'avg_price' => round((float) $item->avg_price, 2),
                'percentage' => $percent,
            ];
        })->toArray();

        if ($otherQty > 0) {
            $otherSpent = (float) DB::table('invoice_items')
                ->whereNotIn('description', $topPurchasedQuery->pluck('description'))
                ->sum('total');

            $topPurchasedProducts[] = [
                'description' => 'Otros Repuestos / Insumos',
                'total_quantity' => $otherQty,
                'total_spent' => round($otherSpent, 2),
                'avg_price' => 0.0,
                'percentage' => $allPurchasedQty > 0 ? round(($otherQty / $allPurchasedQty) * 100, 1) : 0,
            ];
        }

        // 8. Work Orders Report (Rendimiento de Técnicos / OTs 100% Real)
        // OT Totales grouped by status
        $otTotales = \App\Models\WorkOrder\WorkOrder::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return ['status' => $item->status ?: 'Sin estado', 'count' => (int) $item->count];
            });

        // SLA (Días para cerrar OTs)
        $slas = \App\Models\WorkOrder\WorkOrder::whereIn('status', ['CERRADA_OK', 'FINALIZADA', 'FINALIZADO'])
            ->get(['created_at', 'updated_at']);
        
        $slaBuckets = [
            '1 día' => 0,
            '2-3 días' => 0,
            '4-7 días' => 0,
            '+8 días' => 0
        ];
        
        foreach ($slas as $sla) {
            if ($sla->created_at && $sla->updated_at) {
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
        }

        // Técnicos (Work Orders per technician by status)
        $technicianReportRaw = DB::table('work_order_technicians')
            ->join('work_orders', 'work_order_technicians.work_order_id', '=', 'work_orders.id')
            ->join('employees', 'work_order_technicians.employee_id', '=', 'employees.id')
            ->select('employees.first_name', 'employees.last_name', 'work_orders.status', DB::raw('count(*) as count'))
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'work_orders.status')
            ->get();

        $techniciansData = [];
        foreach ($technicianReportRaw as $row) {
            $techName = trim($row->first_name . ' ' . substr($row->last_name, 0, 1) . '.');
            if (!isset($techniciansData[$techName])) {
                $techniciansData[$techName] = [];
            }
            $techniciansData[$techName][$row->status ?: 'General'] = (int) $row->count;
        }

        // Resumen de Compras Globales
        $totalPurchasesCount = Invoice::count();

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
                    'total_purchases_spent' => round($allPurchasesTotal, 2),
                    'total_purchases_count' => $totalPurchasesCount,
                    'work_orders_report' => [
                        'ot_totales' => $otTotales,
                        'sla' => $slaBuckets,
                        'technicians' => $techniciansData,
                    ]
                ],
                'sales_by_type' => [
                    'products' => round($productRevenue, 2),
                    'services' => round($serviceRevenue, 2)
                ],
                'top_products' => $topProducts,
                'top_purchased_products' => $topPurchasedProducts,
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

    /**
     * Get detailed breakdown of monthly sales separated by Products and Services,
     * ordered from highest to lowest (mayor a menor).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function monthlySalesBreakdown(\Illuminate\Http\Request $request): JsonResponse
    {
        $selectedMonthStr = $request->get('month') ?: Carbon::now('America/Guayaquil')->format('Y-m');

        try {
            $carbonMonth = Carbon::createFromFormat('Y-m', $selectedMonthStr, 'America/Guayaquil')->startOfMonth();
        } catch (\Exception $e) {
            $carbonMonth = Carbon::now('America/Guayaquil')->startOfMonth();
            $selectedMonthStr = $carbonMonth->format('Y-m');
        }

        $startOfMonth = $carbonMonth->copy()->startOfMonth();
        $endOfMonth = $carbonMonth->copy()->endOfMonth();
        $sortBy = $request->get('sort_by', 'revenue'); // 'revenue' | 'quantity'

        // Spanish month name
        $monthName = $carbonMonth->locale('es')->isoFormat('MMMM YYYY');
        $monthName = ucfirst($monthName);

        // Fetch sales details aggregated by item
        $details = SaleDetail::select(
                'sale_details.product_id',
                DB::raw('COALESCE(products.description, sale_details.description) as item_name'),
                'products.sku',
                DB::raw('COALESCE(products.item_type, 2) as item_type'),
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.total) as total_revenue'),
                DB::raw('AVG(sale_details.price) as avg_price'),
                DB::raw('COUNT(DISTINCT sale_details.sale_id) as sales_count')
            )
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_details.product_id')
            ->where('sales.status', '!=', 'draft')
            ->where('sales.status', '!=', 'canceled')
            ->where('sales.document_type', '!=', 'quote')
            ->whereBetween('sales.created_at', [$startOfMonth, $endOfMonth])
            ->groupBy('sale_details.product_id', 'item_name', 'products.sku', 'products.item_type')
            ->get();

        $productsList = [];
        $servicesList = [];

        foreach ($details as $row) {
            $item = [
                'product_id' => $row->product_id,
                'name' => $row->item_name ?: 'Sin descripción',
                'sku' => $row->sku ?: 'S/N',
                'item_type' => (int) $row->item_type,
                'quantity' => (float) $row->total_quantity,
                'revenue' => (float) $row->total_revenue,
                'avg_price' => (float) $row->avg_price,
                'sales_count' => (int) $row->sales_count,
            ];

            if ((int) $row->item_type === 1) {
                $productsList[] = $item;
            } else {
                $servicesList[] = $item;
            }
        }

        // Sort mayor a menor
        if ($sortBy === 'quantity') {
            usort($productsList, fn($a, $b) => $b['quantity'] <=> $a['quantity']);
            usort($servicesList, fn($a, $b) => $b['quantity'] <=> $a['quantity']);
        } else {
            usort($productsList, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
            usort($servicesList, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        }

        // Assign rankings
        foreach ($productsList as $i => &$p) {
            $p['rank'] = $i + 1;
        }
        unset($p);

        foreach ($servicesList as $i => &$s) {
            $s['rank'] = $i + 1;
        }
        unset($s);

        // Summaries
        $totalProductsRevenue = array_sum(array_column($productsList, 'revenue'));
        $totalProductsQty = array_sum(array_column($productsList, 'quantity'));
        $totalServicesRevenue = array_sum(array_column($servicesList, 'revenue'));
        $totalServicesQty = array_sum(array_column($servicesList, 'quantity'));

        $grandTotalRevenue = $totalProductsRevenue + $totalServicesRevenue;
        $grandTotalQty = $totalProductsQty + $totalServicesQty;

        // Available active months list
        $availableMonthsRaw = Sale::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month_key, COUNT(*) as count')
            ->where('status', '!=', 'draft')
            ->where('status', '!=', 'canceled')
            ->where('document_type', '!=', 'quote')
            ->groupBy('month_key')
            ->orderByDesc('month_key')
            ->take(12)
            ->get();

        $availableMonths = [];
        foreach ($availableMonthsRaw as $m) {
            $cDate = Carbon::createFromFormat('Y-m', $m->month_key, 'America/Guayaquil');
            $label = ucfirst($cDate->locale('es')->isoFormat('MMMM YYYY'));
            $availableMonths[] = [
                'key' => $m->month_key,
                'label' => $label,
            ];
        }

        $hasCurrent = false;
        foreach ($availableMonths as $am) {
            if ($am['key'] === $selectedMonthStr) {
                $hasCurrent = true;
                break;
            }
        }
        if (!$hasCurrent) {
            array_unshift($availableMonths, [
                'key' => $selectedMonthStr,
                'label' => $monthName,
            ]);
        }

        return response()->json([
            'success' => true,
            'month_key' => $selectedMonthStr,
            'month_name' => $monthName,
            'sort_by' => $sortBy,
            'summary' => [
                'grand_total_revenue' => $grandTotalRevenue,
                'grand_total_quantity' => $grandTotalQty,
                'products_revenue' => $totalProductsRevenue,
                'products_quantity' => $totalProductsQty,
                'products_unique_count' => count($productsList),
                'services_revenue' => $totalServicesRevenue,
                'services_quantity' => $totalServicesQty,
                'services_unique_count' => count($servicesList),
            ],
            'products' => $productsList,
            'services' => $servicesList,
            'available_months' => $availableMonths,
        ]);
    }
}

