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

            // Mapear con datos listos para el calendario y formato de WhatsApp
            $formattedEvents = $events->map(function ($reminder) {
                $client = $reminder->client;
                $vehicle = $reminder->vehicle;
                $clientPhone = preg_replace('/[^0-9]/', '', $client->phone ?? '');
                $clientName = $client->full_name ?? ($client->name ?? 'Estimado cliente');
                $plate = $vehicle->license_plate ?? '';
                $targetKm = number_format($reminder->target_mileage);

                // Mensaje dinámico de WhatsApp según la categoría
                $customMessages = [
                    'amortiguadores' => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🔧 Te recordamos que según tu kilometraje estimado ({$targetKm} km), tu vehículo (Placa: {$plate}) ya cumple el ciclo recomendado para la revisión y cambio de amortiguadores y suspensión. ¿Deseas agendar un turno esta semana?",
                    'distribucion'   => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗⚙️ Te recordamos que según tu kilometraje estimado ({$targetKm} km), tu vehículo (Placa: {$plate}) está próximo a cumplir el ciclo preventivo del Kit de Distribución. ¿Deseas agendar una revisión preventiva?",
                    'inyectores'     => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗⚡ Te recordamos que tu vehículo (Placa: {$plate}) está próximo a los {$targetKm} km, momento ideal para realizar el afinamiento y limpieza de inyectores. ¿Deseas agendar tu cita?",
                    'frenos'         => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🛑 Por tu seguridad, te recordamos que según tu kilometraje estimado ({$targetKm} km), tu vehículo (Placa: {$plate}) requiere su ABC y revisión de frenos. ¿Deseas agendar un turno?",
                    'alineacion'     => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🛞 Te recordamos que tu vehículo (Placa: {$plate}) está próximo a los {$targetKm} km, ideal para alineación, balanceo y rotación de llantas. ¿Te reservamos un espacio?",
                    'aceite'         => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🛢️ Te recordamos que tu vehículo (Placa: {$plate}) está próximo a cumplir los {$targetKm} km para su cambio de aceite y filtros. ¿Deseas agendar tu cita?",
                    'general'        => "Hola {$clientName}, te saludamos de tu taller mecánico. 🚗🔧 Estimamos que tu vehículo (Placa: {$plate}) está próximo a los {$targetKm} km para su mantenimiento preventivo periódico. ¿Deseas que te reservemos un turno?",
                ];

                $whatsappText = $customMessages[$reminder->service_category] ?? $customMessages['general'];
                $whatsappUrl = "https://api.whatsapp.com/send?phone={$clientPhone}&text=" . rawurlencode($whatsappText);

                // Colores e íconos por categoría
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

                return [
                    'id'                   => $reminder->id,
                    'title'                => $reminder->title,
                    'description'          => $reminder->description,
                    'service_category'     => $reminder->service_category,
                    'category_color'       => $categoryColors[$reminder->service_category] ?? 'primary',
                    'category_icon'        => $categoryIcons[$reminder->service_category] ?? 'ri-tools-line',
                    'scheduled_date'       => $reminder->scheduled_date->format('Y-m-d'),
                    'day'                  => (int) $reminder->scheduled_date->format('d'),
                    'target_mileage'       => $reminder->target_mileage,
                    'last_service_mileage' => $reminder->last_service_mileage,
                    'avg_daily_km'         => (float) $reminder->avg_daily_km,
                    'status'               => $reminder->status,
                    'notified_at'          => $reminder->notified_at ? $reminder->notified_at->format('Y-m-d H:i:s') : null,
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
                        'model'         => $vehicle->model,
                        'year'          => $vehicle->year,
                        'usage_type'    => $vehicle->usage_type,
                    ] : null,
                    'whatsapp_url'         => $whatsappUrl,
                    'whatsapp_text'        => $whatsappText,
                ];
            });

            return response()->json([
                'success' => true,
                'data'    => $formattedEvents,
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
     * Enviar notificación (Email / WhatsApp) y registrar estado.
     */
    public function notify(Request $request, int $id): JsonResponse
    {
        try {
            $reminder = MaintenanceReminder::with(['client', 'vehicle'])->findOrFail($id);
            $channel = $request->input('channel', 'whatsapp'); // 'whatsapp', 'email', 'both'

            if (($channel === 'email' || $channel === 'both') && $reminder->client && $reminder->client->email) {
                try {
                    $reminder->client->notify(new MaintenanceReminderNotification($reminder));
                } catch (Exception $mailError) {
                    Log::warning('No se pudo enviar correo de recordatorio:', ['error' => $mailError->getMessage()]);
                }
            }

            $reminder->update([
                'status'               => 'notified',
                'notified_at'          => now(),
                'notification_channel' => $channel,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificación registrada exitosamente.',
                'data'    => $reminder,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la notificación.',
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
