<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\MaintenanceReminderMail;
use App\Models\MaintenanceReminder;
use App\Notifications\MaintenanceReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class MaintenanceReminderController extends Controller
{
    /**
     * Listar eventos de mantenimiento preventivo para el calendario.
     */
    public function getCalendarEvents(Request $request): JsonResponse
    {
        try {
            $month = (int) $request->query('month', now()->month);
            $year = (int) $request->query('year', now()->year);

            $events = MaintenanceReminder::with(['client', 'vehicle'])
                ->whereYear('scheduled_date', $year)
                ->whereMonth('scheduled_date', $month)
                ->whereIn('status', ['pending', 'notified', 'scheduled', 'completed'])
                ->orderBy('scheduled_date', 'asc')
                ->get();

            // Próximo evento futuro si el mes actual está vacío
            $nextEvent = null;
            if ($events->isEmpty()) {
                $endOfCurrentMonth = Carbon::create($year, $month, 1)->endOfMonth();
                $next = MaintenanceReminder::where('scheduled_date', '>=', $endOfCurrentMonth)
                    ->whereIn('status', ['pending', 'notified', 'scheduled'])
                    ->orderBy('scheduled_date', 'asc')
                    ->first();

                if ($next) {
                    $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    $nextMonthIdx = (int) $next->scheduled_date->format('n') - 1;
                    $nextEvent = [
                        'date'       => $next->scheduled_date->format('Y-m-d'),
                        'month'      => (int) $next->scheduled_date->format('n'),
                        'year'       => (int) $next->scheduled_date->format('Y'),
                        'month_name' => $months[$nextMonthIdx] ?? 'Próximo mes',
                        'title'      => $next->title,
                    ];
                }
            }

            $formattedEvents = $events->map(fn($reminder) => $this->formatReminder($reminder));

            $pendingCount = $events->where('status', 'pending')->count();
            $notifiedCount = $events->where('status', 'notified')->count();

            return response()->json([
                'success' => true,
                'data'    => $formattedEvents,
                'meta'    => [
                    'total_events'   => $events->count(),
                    'pending_count'  => $pendingCount,
                    'notified_count' => $notifiedCount,
                    'next_event'     => $nextEvent,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener eventos de mantenimiento:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar eventos del calendario.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar próximos mantenimientos pendientes o proyectados (sin importar el mes).
     */
    public function getUpcomingEvents(Request $request): JsonResponse
    {
        try {
            $limit = (int) $request->query('limit', 15);
            $status = $request->query('status', 'all'); // 'pending', 'notified', 'all'

            $query = MaintenanceReminder::with(['client', 'vehicle'])
                ->whereDate('scheduled_date', '>=', now()->subDays(7)->toDateString())
                ->orderBy('scheduled_date', 'asc');

            if ($status === 'pending') {
                $query->where('status', 'pending');
            } elseif ($status === 'notified') {
                $query->where('status', 'notified');
            } else {
                $query->whereIn('status', ['pending', 'notified', 'scheduled']);
            }

            $events = $query->take($limit)->get();

            return response()->json([
                'success' => true,
                'data'    => $events->map(fn($r) => $this->formatReminder($r)),
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener próximos mantenimientos:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar próximos mantenimientos.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resumen estructurado para el Drawer Lateral (Pestañas: Hoy, Próximos 7 días, Vencidos) y Badge del Navbar.
     */
    public function getDrawerSummary(Request $request): JsonResponse
    {
        try {
            $today = Carbon::today()->toDateString();
            $next7Days = Carbon::today()->addDays(7)->toDateString();

            // 1. Mantenimientos de HOY (programados para la fecha actual)
            $todayEvents = MaintenanceReminder::with(['client', 'vehicle'])
                ->whereDate('scheduled_date', $today)
                ->whereIn('status', ['pending', 'notified', 'scheduled'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('scheduled_date', 'asc')
                ->get();

            // 2. Mantenimientos PRÓXIMOS (en los siguientes 7 días, sin incluir hoy)
            $upcomingEvents = MaintenanceReminder::with(['client', 'vehicle'])
                ->whereDate('scheduled_date', '>', $today)
                ->whereDate('scheduled_date', '<=', $next7Days)
                ->whereIn('status', ['pending', 'notified', 'scheduled'])
                ->orderBy('scheduled_date', 'asc')
                ->get();

            // 3. Mantenimientos VENCIDOS (cuya fecha estimada ya pasó y siguen pendientes)
            $overdueEvents = MaintenanceReminder::with(['client', 'vehicle'])
                ->whereDate('scheduled_date', '<', $today)
                ->where('status', 'pending')
                ->orderBy('scheduled_date', 'asc')
                ->get();

            $pendingTodayCount = $todayEvents->where('status', 'pending')->count();
            $badgeTotal = $pendingTodayCount + $overdueEvents->count();

            return response()->json([
                'success' => true,
                'data'    => [
                    'today'    => $todayEvents->map(fn($r) => $this->formatReminder($r)),
                    'upcoming' => $upcomingEvents->map(fn($r) => $this->formatReminder($r)),
                    'overdue'  => $overdueEvents->map(fn($r) => $this->formatReminder($r)),
                ],
                'counts'  => [
                    'today'         => $todayEvents->count(),
                    'pending_today' => $pendingTodayCount,
                    'upcoming'      => $upcomingEvents->count(),
                    'overdue'       => $overdueEvents->count(),
                    'badge_total'   => $badgeTotal,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error al obtener resumen de recordatorios para Drawer:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar recordatorios para el drawer lateral.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Formatear entidad de MaintenanceReminder con metadatos de WhatsApp, correo e interfaz.
     */
    private function formatReminder(MaintenanceReminder $reminder): array
    {
        $client = $reminder->client;
        $vehicle = $reminder->vehicle;
        $rawClientPhone = $client->phone ?? '';
        $cellPhoneForWhatsApp = $this->formatEcuadorianCellPhone($rawClientPhone);
        $phoneType = $this->detectPhoneType($rawClientPhone);
        $hasCellPhone = !empty($cellPhoneForWhatsApp);

        $clientName = $client->full_name ?? ($client->name ?? 'Estimado cliente');
        $plate = $vehicle->license_plate ?? 'Vehículo';
        $targetKm = number_format($reminder->target_mileage);
        $intervalKm = number_format($reminder->interval_km ?: 10000);

        // Mensajes WhatsApp personalizados
        $customMessages = [
            'amortiguadores' => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🔧 Te recordamos que según tu kilometraje ({$targetKm} km), tu vehículo (Placa: {$plate}) ya cumplió el ciclo de los {$intervalKm} km para la revisión de amortiguadores y suspensión. ¿Deseas agendar un turno esta semana?",
            'distribucion'   => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗⚙️ Te informamos que tu vehículo (Placa: {$plate}) ya cumplió el ciclo preventivo de los {$intervalKm} km para el Kit de Distribución ({$targetKm} km). ¿Deseas agendar tu revisión?",
            'inyectores'     => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗⚡ Tu vehículo (Placa: {$plate}) ya completó los {$intervalKm} km recomendados ({$targetKm} km), momento indicado para afinamiento y limpieza de inyectores. ¿Deseas agendar tu cita?",
            'frenos'         => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🛑 Por tu seguridad, te informamos que tu vehículo (Placa: {$plate}) ya completó el ciclo de los {$intervalKm} km ({$targetKm} km) y requiere su ABC y revisión de frenos. ¿Deseas agendar un turno?",
            'alineacion'     => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🛞 Tu vehículo (Placa: {$plate}) ya cumplió los {$intervalKm} km ({$targetKm} km), necesario para alineación, balanceo y rotación de llantas. ¿Te reservamos un espacio?",
            'aceite'         => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🛢️ Te informamos que tu vehículo (Placa: {$plate}) ya completó el ciclo de los {$intervalKm} km recomendados desde su último servicio ({$targetKm} km). Es momento de realizar su cambio de aceite y filtros. ¿Deseas agendar tu cita?",
            'general'        => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🔧 Tu vehículo (Placa: {$plate}) ya ha cumplido los {$intervalKm} km ({$targetKm} km) para su mantenimiento preventivo periódico. ¿Deseas que te reservemos un turno?",
        ];

        $whatsappText = $customMessages[$reminder->service_category] ?? $customMessages['general'];
        $whatsappUrl = $hasCellPhone
            ? "https://api.whatsapp.com/send?phone={$cellPhoneForWhatsApp}&text=" . rawurlencode($whatsappText)
            : null;

        $categoryColors = [
            'aceite'         => 'primary',
            'frenos'         => 'error',
            'amortiguadores' => 'deep-purple',
            'inyectores'     => 'success',
            'distribucion'   => 'warning',
            'alineacion'     => 'info',
            'general'        => 'secondary',
        ];

        $categoryIcons = [
            'aceite'         => 'ri-oil-line',
            'frenos'         => 'ri-shield-cross-line',
            'amortiguadores' => 'ri-car-line',
            'inyectores'     => 'ri-flashlight-line',
            'distribucion'   => 'ri-settings-4-line',
            'alineacion'     => 'ri-compass-3-line',
            'general'        => 'ri-tools-line',
        ];

        $serviceTitles = [
            'amortiguadores' => 'Revisión y Cambio de Amortiguadores / Suspensión',
            'distribucion'   => 'Cambio de Banda / Kit de Distribución',
            'inyectores'     => 'Afinamiento y Limpieza de Inyectores',
            'frenos'         => 'Mantenimiento y ABC de Frenos',
            'alineacion'     => 'Alineación, Balanceo y Rotación de Neumáticos',
            'aceite'         => 'Cambio de Aceite y Filtros',
            'general'        => 'Mantenimiento Preventivo Periódico',
        ];
        $serviceName = $serviceTitles[$reminder->service_category] ?? 'Mantenimiento Preventivo';

        $vehicleBrandName = $vehicle ? ($vehicle->brand_name ?: (config('vehicle_brands')[(int)$vehicle->brand] ?? ($vehicle->brand ?? ''))) : '';
        $vehicleModelName = $vehicle ? trim("{$vehicleBrandName} {$vehicle->model}") : 'su vehículo';

        $defaultEmailSubject = "Mantenimiento Preventivo ({$intervalKm} KM Cumplidos) - Placa {$plate}";
        $defaultEmailBody = "Estimado(a) {$clientName},\n\n"
            . "De acuerdo con nuestro control técnico de mantenimiento vehicular, le informamos que su vehículo {$vehicleModelName} (Placa: {$plate}) ha cumplido el ciclo de los {$intervalKm} KM recomendados desde su último servicio, alcanzando aproximadamente los {$targetKm} KM.\n\n"
            . "Servicio correspondiente: {$serviceName}.\n\n"
            . "Le sugerimos agendar su turno para la inspección y mantenimiento correspondiente, garantizando así la seguridad, rendimiento y óptimo funcionamiento de su vehículo.";

        $scheduledCarbon = Carbon::parse($reminder->scheduled_date);
        $diffDays = (int) now()->startOfDay()->diffInDays($scheduledCarbon->copy()->startOfDay(), false);

        return [
            'id'                    => $reminder->id,
            'title'                 => $reminder->title,
            'description'           => $reminder->description,
            'service_category'      => $reminder->service_category,
            'service_name'          => $serviceName,
            'category_color'        => $categoryColors[$reminder->service_category] ?? 'primary',
            'category_icon'         => $categoryIcons[$reminder->service_category] ?? 'ri-tools-line',
            'scheduled_date'        => $scheduledCarbon->format('Y-m-d'),
            'day'                   => (int) $scheduledCarbon->format('d'),
            'target_mileage'        => $reminder->target_mileage,
            'last_service_mileage'  => $reminder->last_service_mileage,
            'avg_daily_km'          => (float) $reminder->avg_daily_km,
            'status'                => $reminder->status,
            'notified_at'           => $reminder->notified_at ? Carbon::parse($reminder->notified_at)->format('Y-m-d H:i') : null,
            'notification_channel'  => $reminder->notification_channel,
            'has_email'             => !empty($client?->email),
            'has_phone'             => !empty($rawClientPhone),
            'has_cellphone'         => $hasCellPhone,
            'phone_type'            => $phoneType,
            'client_phone'          => $rawClientPhone,
            'whatsapp_phone'        => $cellPhoneForWhatsApp,
            'days_until'            => $diffDays,
            'is_today'              => $scheduledCarbon->isToday(),
            'is_overdue'            => $scheduledCarbon->isPast() && !$scheduledCarbon->isToday(),
            'default_email_subject' => $defaultEmailSubject,
            'default_email_body'    => $defaultEmailBody,
            'client'                => $client ? [
                'id'        => $client->id,
                'full_name' => $client->full_name ?? ($client->name ?? ''),
                'phone'     => $client->phone,
                'email'     => $client->email,
            ] : null,
            'vehicle'               => $vehicle ? [
                'id'            => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
                'brand'         => $vehicle->brand,
                'brand_name'    => $vehicleBrandName,
                'model'         => $vehicle->model,
                'year'          => $vehicle->year,
                'usage_type'    => $vehicle->usage_type,
            ] : null,
            'whatsapp_url'          => $whatsappUrl,
            'whatsapp_text'         => $whatsappText,
        ];
    }

    /**
     * Normaliza y valida si un número telefónico de Ecuador es celular móvil para WhatsApp.
     * Si es celular móvil:
     *   - 10 dígitos iniciando en '09' (ej. 0999179988) -> '593' . substr($clean, 1) -> 593999179988
     *   - 9 dígitos iniciando en '9' (ej. 999179988) -> '593' . $clean -> 593999179988
     *   - 12 dígitos iniciando en '5939' -> 593999179988
     * Si es convencional (ej. 002432211, 022432211, etc.) o inválido, retorna null.
     */
    private function formatEcuadorianCellPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // Si contiene múltiples teléfonos separados por barras, guiones o comas, evaluar cada uno
        $parts = preg_split('/[\/,\-;|\s]+/', $phone);
        foreach ($parts as $part) {
            $clean = preg_replace('/[^0-9]/', '', $part);
            if (empty($clean)) {
                continue;
            }

            if (str_starts_with($clean, '005939') && strlen($clean) === 14) {
                return substr($clean, 2);
            }

            if (strlen($clean) === 12 && str_starts_with($clean, '5939')) {
                return $clean;
            }

            if (strlen($clean) === 10 && str_starts_with($clean, '09')) {
                return '593' . substr($clean, 1);
            }

            if (strlen($clean) === 9 && str_starts_with($clean, '9')) {
                return '593' . $clean;
            }
        }

        // Si se limpió completo y no por partes
        $cleanTotal = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanTotal) === 10 && str_starts_with($cleanTotal, '09')) {
            return '593' . substr($cleanTotal, 1);
        }
        if (strlen($cleanTotal) === 12 && str_starts_with($cleanTotal, '5939')) {
            return $cleanTotal;
        }
        if (strlen($cleanTotal) === 9 && str_starts_with($cleanTotal, '9')) {
            return '593' . $cleanTotal;
        }

        return null;
    }

    /**
     * Detecta el tipo de número telefónico: 'cellphone' (celular móvil), 'conventional' (línea fija), o 'none' (vacío).
     */
    private function detectPhoneType(?string $phone): string
    {
        if (empty($phone)) {
            return 'none';
        }

        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (empty($clean)) {
            return 'none';
        }

        if ($this->formatEcuadorianCellPhone($phone) !== null) {
            return 'cellphone';
        }

        return 'conventional';
    }

    /**
     * Enviar notificación (Email / WhatsApp) y registrar estado.
     */
    public function notify(Request $request, int $id): JsonResponse
    {
        try {
            $reminder = MaintenanceReminder::with(['client', 'vehicle'])->findOrFail($id);
            $channel = $request->input('channel', 'whatsapp'); // 'whatsapp', 'email', 'both'

            if ($channel === 'email' || $channel === 'both') {
                $recipientEmail = trim($request->input('custom_email', $reminder->client?->email ?? ''));

                if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El cliente no tiene un correo válido registrado o el correo ingresado es inválido.',
                    ], 422);
                }

                // Si el cliente no tenía correo registrado, guardarlo para futuras notificaciones
                if ($reminder->client && empty($reminder->client->email)) {
                    $reminder->client->update(['email' => $recipientEmail]);
                }

                // Enviar la notificación por correo al destinatario con asunto y cuerpo personalizado
                $customSubject = $request->input('subject');
                $customMessage = $request->input('message');
                Mail::to($recipientEmail)->send(new MaintenanceReminderMail($reminder, $customSubject, $customMessage));
            }

            $reminder->update([
                'status'               => 'notified',
                'notified_at'          => now(),
                'notification_channel' => $channel,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación enviada y registrada exitosamente.',
                'data'    => $this->formatReminder($reminder),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al enviar notificación de mantenimiento:', [
                'reminder_id' => $id,
                'error'       => $e->getMessage(),
                'file'        => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la notificación: ' . $e->getMessage(),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint específico POST /api/maintenance-reminders/{id}/mark-notified
     * Registra en BD cuándo y por qué canal se notificó al cliente (ej. WhatsApp manual o llamada).
     */
    public function markNotified(Request $request, int $id): JsonResponse
    {
        try {
            $reminder = MaintenanceReminder::with(['client', 'vehicle'])->findOrFail($id);
            $channel = $request->input('channel', 'whatsapp'); // 'whatsapp', 'email', 'phone', 'manual'

            $reminder->update([
                'status'               => 'notified',
                'notified_at'          => Carbon::now(),
                'notification_channel' => $channel,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio marcado como notificado correctamente.',
                'data'    => $this->formatReminder($reminder),
            ]);
        } catch (Exception $e) {
            Log::error('Error al marcar recordatorio como notificado:', [
                'reminder_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar recordatorio como notificado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar estado del recordatorio (ej. agendado, completado, cancelado).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,notified,scheduled,completed,cancelled',
        ]);

        $reminder = MaintenanceReminder::findOrFail($id);
        $reminder->update([
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente.',
            'data'    => $reminder,
        ]);
    }
}
