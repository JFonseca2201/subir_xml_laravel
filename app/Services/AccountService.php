<?php

namespace App\Services;

use App\Models\Account;

class AccountService
{
    /**
     * Obtener cuenta específica según tipo de pago
     */
    public static function getCuentaPorMetodoPago(string $paymentMethod): ?Account
    {
        return match ($paymentMethod) {
            'cash' => self::getCuentaEfectivo(),
            'transfer' => self::getCuentaTransferencia(),
            default => null,
        };
    }

    /**
     * Obtener cuenta para pagos en efectivo (Caja Chica)
     */
    public static function getCuentaEfectivo(): ?Account
    {
        return Account::where('code', 'CAJA_CHICA')->first();
    }

    /**
     * Obtener cuenta para transferencias (Banco Pichincha o Banco Guayaquil)
     */
    public static function getCuentaTransferencia(): ?Account
    {
        // Por defecto usa Banco Pichincha, pero se puede configurar
        return Account::where('code', 'BPICH')->first();
    }

    /**
     * Obtener cuenta específica para aportes de socios (Banco Guayaquil)
     */
    public static function getCuentaAportesSocios(): ?Account
    {
        return Account::where('code', 'BGA')->first();
    }

    /**
     * Obtener cuenta específica por código
     */
    public static function getCuentaPorCodigo(string $codigo): ?Account
    {
        return Account::where('code', $codigo)->first();
    }

    /**
     * Obtener todas las cuentas bancarias para transferencias
     */
    public static function getCuentasBancarias()
    {
        return Account::where('type', 'bank')
            ->where('state', true)
            ->get();
    }

    /**
     * Validar si un monto puede ser procesado por una cuenta
     */
    public static function validarSaldoCuenta(Account $cuenta, float $monto): bool
    {
        return ($cuenta->current_balance ?? 0) >= $monto;
    }

    /**
     * Obtener descripción del tipo de cuenta
     */
    public static function getDescripcionTipoCuenta(Account $cuenta): string
    {
        return match ($cuenta->type) {
            'cash' => 'Caja Chica (Efectivo)',
            'bank' => "Banco: {$cuenta->bank_name}",
            default => 'Desconocido',
        };
    }
}
