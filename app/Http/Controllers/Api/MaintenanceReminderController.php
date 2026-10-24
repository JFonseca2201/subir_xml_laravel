<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceReminder;
use App\Notifications\MaintenanceReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Formatear entidad de MaintenanceReminder con metadatos de WhatsApp, correo e interfaz.
     */
    private function formatReminder(MaintenanceReminder $reminder): array
    {
        $client = $reminder->client;
        $vehicle = $reminder->vehicle;
        $clientPhone = preg_replace('/[^0-9]/', '', $client->phone ?? '');
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
        $whatsappUrl = "https://api.whatsapp.com/send?phone={$clientPhone}&text=" . rawurlencode($whatsappText);

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

        $scheduledCarbon = Carbon::parse($reminder->scheduled_date);
        $diffDays = (int) now()->startOfDay()->diffInDays($scheduledCarbon->copy()->startOfDay(), false);

        return [
            'id'                   => $reminder->id,
            'title'                => $reminder->title,
            'description'          => $reminder->description,
            'service_category'     => $reminder->service_category,
            'category_color'       => $categoryColors[$reminder->service_category] ?? 'primary',
            'category_icon'        => $categoryIcons[$reminder->service_category] ?? 'ri-tools-line',
            'scheduled_date'       => $scheduledCarbon->format('Y-m-d'),
            'day'                  => (int) $scheduledCarbon->format('d'),
            'target_mileage'       => $reminder->target_mileage,
            'last_service_mileage' => $reminder->last_service_mileage,
            'avg_daily_km'         => (float) $reminder->avg_daily_km,
            'status'               => $reminder->status,
            'notified_at'          => $reminder->notified_at ? Carbon::parse($reminder->notified_at)->format('Y-m-d H:i') : null,
            'notification_channel' => $reminder->notification_channel,
            'has_email'            => !empty($client?->email),
            'has_phone'            => !empty($client?->phone),
            'days_until'           => $diffDays,
            'is_today'             => $scheduledCarbon->isToday(),
            'is_overdue'           => $scheduledCarbon->isPast() && !$scheduledCarbon->isToday(),
            'client'               => $client ? [
                'id'        => $client->id,
                'full_name' => $client->full_name ?? ($client->name ?? ''),
                'phone'     => $client->phone,
                'email'     => $client->email,
            ] : null,
            'vehicle'              => $vehicle ? [
                'id'            => $vehicle->id,
                'license_plate' => $vehicle->license_plate,
                'brand'         => $vehicle->brand,
                'brand_name'    => $vehicle->brand_name ?: (config('vehicle_brands')[(int)$vehicle->brand] ?? $vehicle->brand),
                'model'         => $vehicle->model,
                'year'          => $vehicle->year,
                'usage_type'    => $vehicle->usage_type,
            ] : null,
            'whatsapp_url'         => $whatsappUrl,
            'whatsapp_text'        => $whatsappText,
        ];
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

                // Enviar la notificación por correo al destinatario
                \Illuminate\Support\Facades\Notification::route('mail', $recipientEmail)
                    ->notify(new MaintenanceReminderNotification($reminder));
            }

            $reminder->update([
                'status'               => 'notified',
                'notified_at'          => now(),
                'notification_channel' => $channel,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación enviada y registrada exitosamente.',
                'data'    => $reminder,
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
