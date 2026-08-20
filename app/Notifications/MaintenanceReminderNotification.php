<?php

namespace App\Notifications;

use App\Models\MaintenanceReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceReminderNotification extends Notification
{
    use Queueable;

    protected $reminder;

    public function __construct(MaintenanceReminder $reminder)
    {
        $this->reminder = $reminder;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $vehicle = $this->reminder->vehicle;
        $client = $this->reminder->client;
        $targetKm = number_format($this->reminder->target_mileage);
        $plate = $vehicle->license_plate ?? 'Vehículo';
        $vehicleModel = "{$vehicle->brand} {$vehicle->model}";

        $serviceTitles = [
            'amortiguadores' => 'Revisión y Cambio de Amortiguadores / Suspensión',
            'distribucion'   => 'Cambio de Banda / Kit de Distribución',
            'inyectores'     => 'Afinamiento y Limpieza de Inyectores',
            'frenos'         => 'Mantenimiento y ABC de Frenos',
            'alineacion'     => 'Alineación, Balanceo y Rotación de Neumáticos',
            'aceite'         => 'Cambio de Aceite y Filtros',
            'general'        => 'Mantenimiento Preventivo Periódico',
        ];

        $serviceName = $serviceTitles[$this->reminder->service_category] ?? 'Mantenimiento Preventivo';

        return (new MailMessage)
            ->subject("🔧 Recordatorio Preventivo: {$serviceName} - Placa {$plate}")
            ->greeting("¡Hola, {$client->full_name}!")
            ->line("De acuerdo a nuestro seguimiento de kilometraje, estimamos que tu vehículo **{$vehicleModel} (Placa: {$plate})** está próximo a alcanzar los **{$targetKm} KM**.")
            ->line("Servicio recomendado para este kilometraje:")
            ->line("👉 **{$serviceName}**")
            ->line("Realizar este servicio a tiempo previene desgastes mayores y mantiene la seguridad y rendimiento de tu vehículo.")
            ->action('Contactar al Taller / Agendar Cita', url('/'))
            ->line('¡Gracias por confiar en nuestro servicio profesional!');
    }
}
