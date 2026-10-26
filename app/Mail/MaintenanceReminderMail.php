<?php

namespace App\Mail;

use App\Models\Config\Sucursale;
use App\Models\MaintenanceReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MaintenanceReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public MaintenanceReminder $reminder;
    public array $emailData;

    /**
     * Crear una nueva instancia del mensaje de recordatorio.
     */
    public function __construct(MaintenanceReminder $reminder, ?string $customSubject = null)
    {
        $this->reminder = $reminder;
        $this->reminder->loadMissing(['client', 'vehicle']);

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
        $sucursal = Sucursale::first();
        $companyName = $sucursal->trade_name ?? ($sucursal->name ?? 'TECNICENTRO LUXURY EVYS');
        $companyPhone = $sucursal->phone ?? '';
        $companyAddress = $sucursal->address ?? 'Quito, Ecuador';

        $rawPhone = preg_replace('/[^0-9]/', '', $companyPhone);
        $whatsappUrl = !empty($rawPhone)
            ? "https://api.whatsapp.com/send?phone={$rawPhone}&text=" . rawurlencode("Hola, me comunico sobre el recordatorio de mantenimiento preventivo para mi vehículo placa {$plate}.")
            : null;

        $subject = $customSubject ?: "🔧 Mantenimiento Preventivo ({$intervalKm} KM Cumplidos) - Placa {$plate}";

        $this->emailData = [
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
        ];

        $this->subject($subject);
    }

    /**
     * Construir el mensaje.
     */
    public function build()
    {
        return $this->view('emails.maintenance_reminder', $this->emailData);
    }
}
