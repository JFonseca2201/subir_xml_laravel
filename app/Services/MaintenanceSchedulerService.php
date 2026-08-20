<?php

namespace App\Services;

use App\Models\Vehicles\Vehicle;
use App\Models\WorkOrder\WorkOrder;
use App\Models\MaintenanceReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MaintenanceSchedulerService
{
    // Tasas estándar por tipo de uso del vehículo (cuando no hay historial previo)
    const USAGE_DAILY_KM = [
        'particular' => 35.0,  // ~1,000 km al mes
        'comercial'  => 80.0,  // ~2,400 km al mes (repartos, vehículos de empresa)
        'taxi'       => 130.0, // ~4,000 km al mes (taxis, transporte urbano)
        'pesado'     => 160.0, // ~5,000 km al mes (camiones, transporte interprovincial)
    ];

    // Definición de reglas por categoría de servicio
    const SERVICE_RULES = [
        'amortiguadores' => [
            'interval_km' => 50000,
            'max_months'  => 24,
            'title'       => 'Revisión y Cambio de Amortiguadores / Suspensión',
            'keywords'    => ['amortiguador', 'suspension', 'suspensión', 'base amortiguador', 'rotula', 'rótula', 'terminal', 'buje', 'bieleta', 'cazoleta'],
        ],
        'distribucion' => [
            'interval_km' => 60000,
            'max_months'  => 36,
            'title'       => 'Cambio de Banda / Kit de Distribución',
            'keywords'    => ['distribucion', 'distribución', 'banda distribucion', 'correa distribucion', 'kit distribucion', 'tensor distribucion', 'cadena distribucion'],
        ],
        'inyectores' => [
            'interval_km' => 20000,
            'max_months'  => 12,
            'title'       => 'Afinamiento y Limpieza de Inyectores',
            'keywords'    => ['inyector', 'inyectores', 'bujia', 'bujía', 'bujias', 'afinamiento', 'cuerpo aceleracion', 'aceleración', 'ultrasonido'],
        ],
        'frenos' => [
            'interval_km' => 15000,
            'max_months'  => 12,
            'title'       => 'Mantenimiento y ABC de Frenos',
            'keywords'    => ['freno', 'frenos', 'pastilla', 'pastillas', 'disco freno', 'zapata', 'zapatas', 'liquido freno', 'líquido freno', 'sangrado'],
        ],
        'alineacion' => [
            'interval_km' => 10000,
            'max_months'  => 6,
            'title'       => 'Alineación, Balanceo y Rotación',
            'keywords'    => ['alineacion', 'alineación', 'balanceo', 'rotacion', 'rotación', 'geometria'],
        ],
        'aceite' => [
            'interval_km' => 10000,
            'max_months'  => 6,
            'title'       => 'Cambio de Aceite y Filtros',
            'keywords'    => ['aceite', 'filtro aceite', 'lubricante', 'sintetico', 'sintético', 'semisintetico', 'mineral', '5w30', '10w30', '10w40', '20w50'],
        ],
        'general' => [
            'interval_km' => 10000,
            'max_months'  => 6,
            'title'       => 'Mantenimiento Preventivo Periódico',
            'keywords'    => [],
        ],
    ];

    /**
     * Procesa una orden de trabajo y genera los recordatorios inteligentes según kilometraje y componentes.
     */
    public function scheduleMaintenanceFromWorkOrder(WorkOrder $workOrder): array
    {
        $vehicle = $workOrder->vehicle;
        $client = $workOrder->client;

        if (!$vehicle || !$client) {
            return [];
        }

        $currentMileage = (int) ($workOrder->mileage ?? 0);
        if ($currentMileage <= 0) {
            return [];
        }

        $serviceDate = $workOrder->date ? Carbon::parse($workOrder->date) : Carbon::parse($workOrder->created_at);

        // 1. Calcular el promedio de KM diario real
        $avgDailyKm = $this->calculateDailyKm($vehicle, $workOrder, $currentMileage, $serviceDate);

        // 2. Detectar las categorías de servicio involucradas en la orden de trabajo
        $detectedCategories = $this->detectServiceCategories($workOrder);

        // Si no se detectó ninguna categoría específica, aplicamos mantenimiento general
        if (empty($detectedCategories)) {
            $detectedCategories[] = 'general';
        }

        $createdReminders = [];

        foreach ($detectedCategories as $categoryKey) {
            $rule = self::SERVICE_RULES[$categoryKey] ?? self::SERVICE_RULES['general'];
            $intervalKm = $rule['interval_km'];

            // Para taxis en cambio de aceite, si es mineral o uso severo, se recomienda a 5,000 km
            if ($categoryKey === 'aceite' && in_array(strtolower($vehicle->usage_type ?? ''), ['taxi', 'comercial', 'pesado'])) {
                $intervalKm = 5000;
            }

            // Calcular días requeridos para alcanzar el intervalo
            $daysToTarget = (int) round($intervalKm / $avgDailyKm);

            // Calcular fecha proyectada por kilometraje
            $projectedDate = $serviceDate->copy()->addDays($daysToTarget);

            // Tope por tiempo máximo preventivo
            $maxDateByTime = $serviceDate->copy()->addMonths($rule['max_months']);
            if ($projectedDate->gt($maxDateByTime)) {
                $scheduledDate = $maxDateByTime;
            } else {
                $scheduledDate = $projectedDate;
            }

            $targetMileage = $currentMileage + $intervalKm;

            // Cancelar recordatorios previos de la misma categoría que estén pendientes
            MaintenanceReminder::where('vehicle_id', $vehicle->id)
                ->where('service_category', $categoryKey)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            // Crear el nuevo recordatorio preventivo
            $reminder = MaintenanceReminder::create([
                'client_id'            => $client->id,
                'vehicle_id'           => $vehicle->id,
                'work_order_id'        => $workOrder->id,
                'service_category'     => $categoryKey,
                'interval_km'          => $intervalKm,
                'last_service_mileage' => $currentMileage,
                'target_mileage'       => $targetMileage,
                'avg_daily_km'         => $avgDailyKm,
                'last_service_date'    => $serviceDate->toDateString(),
                'scheduled_date'       => $scheduledDate->toDateString(),
                'title'                => "{$rule['title']} ({$targetMileage} KM)",
                'description'          => "Vehículo {$vehicle->license_plate} ({$vehicle->model}). Tasa de uso: {$avgDailyKm} km/día. Próximo a los {$targetMileage} km.",
                'status'               => 'pending',
            ]);

            $createdReminders[] = $reminder;
        }

        return $createdReminders;
    }

    /**
     * Calcula el promedio de KM diario real basado en historial de servicios o tasa base de uso.
     */
    private function calculateDailyKm(Vehicle $vehicle, WorkOrder $currentWorkOrder, int $currentMileage, Carbon $currentDate): float
    {
        $usageType = strtolower($vehicle->usage_type ?? 'particular');
        $defaultDailyKm = self::USAGE_DAILY_KM[$usageType] ?? self::USAGE_DAILY_KM['particular'];

        // Buscar servicio previo con kilometraje
        $previousService = WorkOrder::where('vehicle_id', $vehicle->id)
            ->where('id', '!=', $currentWorkOrder->id)
            ->whereNotNull('mileage')
            ->where('mileage', '<', $currentMileage)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($previousService) {
            $prevMileage = (int) $previousService->mileage;
            $prevDate = $previousService->date ? Carbon::parse($previousService->date) : Carbon::parse($previousService->created_at);

            $daysBetween = $prevDate->diffInDays($currentDate);
            $kmDiff = $currentMileage - $prevMileage;

            if ($daysBetween >= 2 && $kmDiff > 0) {
                $calculatedDailyKm = round($kmDiff / $daysBetween, 2);

                // Límites de seguridad según tipo de uso
                $minLimit = 5.0;
                $maxLimit = in_array($usageType, ['taxi', 'pesado']) ? 500.0 : 300.0;

                if ($calculatedDailyKm >= $minLimit && $calculatedDailyKm <= $maxLimit) {
                    return $calculatedDailyKm;
                }
            }
        }

        return $defaultDailyKm;
    }

    /**
     * Analiza las descripciones de los ítems de la orden de trabajo para detectar categorías.
     */
    private function detectServiceCategories(WorkOrder $workOrder): array
    {
        $detected = [];
        $items = $workOrder->items;

        if (!$items || $items->isEmpty()) {
            return [];
        }

        foreach ($items as $item) {
            $desc = mb_strtolower($item->description ?? '');
            
            foreach (self::SERVICE_RULES as $categoryKey => $rule) {
                if ($categoryKey === 'general') continue;

                foreach ($rule['keywords'] as $keyword) {
                    if (str_contains($desc, mb_strtolower($keyword))) {
                        if (!in_array($categoryKey, $detected)) {
                            $detected[] = $categoryKey;
                        }
                        break;
                    }
                }
            }
        }

        return $detected;
    }
}
