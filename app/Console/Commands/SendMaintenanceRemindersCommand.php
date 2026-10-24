<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MaintenanceReminder;
use App\Notifications\MaintenanceReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendMaintenanceRemindersCommand extends Command
{
    /**
     * El nombre y firma del comando.
     *
     * @var string
     */
    protected $signature = 'maintenance:send-reminders 
                            {--days=0 : Días de tolerancia después de cumplido el ciclo de kilometraje}
                            {--dry-run : Simular la ejecución sin enviar correos ni cambiar estados}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Envía automáticamente correos electrónicos a los clientes cuyos vehículos ya han cumplido o sobrepasado los 10.000 KM proyectados desde su último servicio';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $daysOffset = (int) $this->option('days');
        $isDryRun = (bool) $this->option('dry-run');

        // Fecha de corte: HOY (ya cumplió los 10.000 km) más cualquier tolerancia
        $targetCutoffDate = Carbon::now()->addDays($daysOffset)->toDateString();

        $this->info("=== PROCESAMIENTO AUTOMATICO DE RECORDATORIOS (10.000 KM CUMPLIDOS) ===");
        $this->line("Buscando vehículos que ya completaron sus 10.000 KM (fecha límite estimada: <comment>{$targetCutoffDate}</comment>)");

        if ($isDryRun) {
            $this->warn("MODO SIMULACIÓN (DRY-RUN) ACTIVO: No se enviarán correos ni se modificarán estados.");
        }

        // Buscar recordatorios pendientes hasta la fecha límite que tengan cliente con correo electrónico
        $reminders = MaintenanceReminder::with(['client', 'vehicle'])
            ->where('status', 'pending')
            ->whereDate('scheduled_date', '<=', $targetCutoffDate)
            ->whereHas('client', function ($query) {
                $query->whereNotNull('email')
                      ->where('email', '!=', '');
            })
            ->orderBy('scheduled_date', 'asc')
            ->get();

        if ($reminders->isEmpty()) {
            $this->info("No hay recordatorios pendientes para enviar correos en este rango de fechas.");
            return Command::SUCCESS;
        }

        $this->info("Se encontraron {$reminders->count()} recordatorio(s) pendiente(s) con correo registrado.");

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($reminders as $reminder) {
            $client = $reminder->client;
            $vehicle = $reminder->vehicle;
            $email = trim($client->email ?? '');
            $plate = $vehicle->license_plate ?? 'S/P';
            $clientName = $client->full_name ?? ($client->name ?? 'Cliente');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn(" -> Omitido ID #{$reminder->id}: Correo inválido '{$email}' (Cliente: {$clientName})");
                $skippedCount++;
                continue;
            }

            $this->line("Procesando ID #{$reminder->id} | {$clientName} ({$email}) | Placa: {$plate} | Meta: " . number_format($reminder->target_mileage) . " KM | Fecha: {$reminder->scheduled_date->format('Y-m-d')}");

            if ($isDryRun) {
                $sentCount++;
                continue;
            }

            try {
                // Enviar la notificación por correo
                $client->notify(new MaintenanceReminderNotification($reminder));

                // Actualizar estado del recordatorio
                $reminder->update([
                    'status'               => 'notified',
                    'notified_at'          => Carbon::now(),
                    'notification_channel' => 'email',
                ]);

                $this->info("    ✓ Correo enviado exitosamente a {$email}");
                $sentCount++;
            } catch (\Throwable $e) {
                $this->error("    ✗ Error al enviar correo a {$email}: " . $e->getMessage());
                Log::error("Error en SendMaintenanceRemindersCommand para Recordatorio #{$reminder->id}: " . $e->getMessage(), [
                    'reminder_id' => $reminder->id,
                    'client_id'   => $client->id ?? null,
                    'email'       => $email,
                ]);
                $failedCount++;
            }
        }

        $this->newLine();
        $this->info("=== RESUMEN DE EJECUCIÓN ===");
        $this->line("Correos enviados: <info>{$sentCount}</info>");
        if ($failedCount > 0) {
            $this->line("Correos fallidos: <error>{$failedCount}</error>");
        }
        if ($skippedCount > 0) {
            $this->line("Omitidos (email inválido): <comment>{$skippedCount}</comment>");
        }

        if (!$isDryRun) {
            Log::info("Cronjob SendMaintenanceRemindersCommand finalizado.", [
                'sent'    => $sentCount,
                'failed'  => $failedCount,
                'skipped' => $skippedCount,
            ]);
        }

        return $failedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
