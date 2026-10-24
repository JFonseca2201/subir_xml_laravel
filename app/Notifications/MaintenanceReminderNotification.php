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

        // Resolver la marca del vehículo desde el catálogo config/vehicle_brands
        $brands = config('vehicle_brands', []);
        $rawBrand = $vehicle->brand ?? null;
        $brandName = '';
        if ($rawBrand !== null && is_numeric($rawBrand)) {
            $brandName = $brands[(int) $rawBrand] ?? '';
        }
        if (empty($brandName)) {
            $brandName = $vehicle->brand_name ?? ($vehicle->brand ?? '');
        }

        $vehicleModel = $vehicle ? trim("{$brandName} {$vehicle->model}") : 'tu vehículo';
        $clientName = $client ? ($client->full_name ?? ($client->name ?? 'Estimado cliente')) : 'Estimado cliente';

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
        $intervalKm = number_format($this->reminder->interval_km ?: 10000);

        // Datos de la empresa / sucursal
        $sucursal = \App\Models\Config\Sucursale::first();
        $companyName = $sucursal->trade_name ?? ($sucursal->name ?? 'TECNICENTRO LUXURY EVYS');
        $companyPhone = $sucursal->phone ?? '';
        $companyAddress = $sucursal->address ?? 'Quito, Ecuador';

        $rawPhone = preg_replace('/[^0-9]/', '', $companyPhone);
        $whatsappUrl = !empty($rawPhone)
            ? "https://api.whatsapp.com/send?phone={$rawPhone}&text=" . rawurlencode("Hola, me comunico sobre el recordatorio de mantenimiento preventivo para mi vehículo placa {$plate}.")
            : null;

        return (new MailMessage)
            ->subject("🔧 Mantenimiento Preventivo ({$intervalKm} KM Cumplidos) - Placa {$plate}")
            ->view('emails.maintenance_reminder', [
                'companyName'    => $companyName,
                'companyPhone'   => $companyPhone,
                'companyAddress' => $companyAddress,
                'clientName'     => $clientName,
                'vehicleModel'   => $vehicleModel,
                'plate'          => $plate,
                'brandName'      => $brandName,
                'targetKm'       => $targetKm,
                'intervalKm'     => $intervalKm,
                'serviceName'    => $serviceName,
                'whatsappUrl'    => $whatsappUrl,
            ]);
    }
}
