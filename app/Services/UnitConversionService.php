<?php

namespace App\Services;

use App\Models\Config\Unit;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para manejar conversiones entre unidades de medida
 * 
 * Este servicio permite convertir cantidades entre diferentes unidades
 * dentro de la misma categoría (count o volume), utilizando factores de conversión.
 */
class UnitConversionService
{
    /**
     * Convierte un valor a la unidad base de su categoría
     * 
     * @param float $value Valor a convertir
     * @param int $unitId ID de la unidad origen
     * @return float Valor convertido a la unidad base
     */
    public function convertToBase(float $value, int $unitId): float
    {
        try {
            $unit = Unit::findOrFail($unitId);
            
            // Si ya es la unidad base, retornar el mismo valor
            if ($unit->is_base) {
                return $value;
            }
            
            // Convertir a la unidad base multiplicando por el factor
            return $value * $unit->factor;
        } catch (\Exception $e) {
            Log::error("Error al convertir a unidad base: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convierte un valor desde la unidad base a una unidad específica
     * 
     * @param float $value Valor en unidad base
     * @param int $unitId ID de la unidad destino
     * @return float Valor convertido a la unidad destino
     */
    public function convertFromBase(float $value, int $unitId): float
    {
        try {
            $unit = Unit::findOrFail($unitId);
            
            // Si ya es la unidad base, retornar el mismo valor
            if ($unit->is_base) {
                return $value;
            }
            
            // Convertir desde la unidad base dividiendo por el factor
            return $value / $unit->factor;
        } catch (\Exception $e) {
            Log::error("Error al convertir desde unidad base: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Convierte un valor entre dos unidades de la misma categoría
     * 
     * @param float $value Valor a convertir
     * @param int $fromUnitId ID de la unidad origen
     * @param int $toUnitId ID de la unidad destino
     * @return float Valor convertido
     */
    public function convert(float $value, int $fromUnitId, int $toUnitId): float
    {
        try {
            $fromUnit = Unit::findOrFail($fromUnitId);
            $toUnit = Unit::findOrFail($toUnitId);
            
            // Verificar que sean de la misma categoría
            if ($fromUnit->category !== $toUnit->category) {
                throw new \InvalidArgumentException("No se pueden convertir unidades de diferentes categorías");
            }
            
            // Convertir a la unidad base primero
            $baseValue = $this->convertToBase($value, $fromUnitId);
            
            // Luego convertir a la unidad destino
            return $this->convertFromBase($baseValue, $toUnitId);
        } catch (\Exception $e) {
            Log::error("Error al convertir entre unidades: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calcula el precio basado en el factor de conversión
     * 
     * @param float $basePrice Precio en unidad base
     * @param int $unitId ID de la unidad para calcular el precio
     * @return float Precio calculado para la unidad especificada
     */
    public function calculatePrice(float $basePrice, int $unitId): float
    {
        try {
            $unit = Unit::findOrFail($unitId);
            
            // Si es la unidad base, retornar el precio base
            if ($unit->is_base) {
                return $basePrice;
            }
            
            // Calcular el precio multiplicando por el factor
            return $basePrice * $unit->factor;
        } catch (\Exception $e) {
            Log::error("Error al calcular precio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene la unidad base de una categoría
     * 
     * @param string $category Categoría (count o volume)
     * @return Unit|null Unidad base o null si no existe
     */
    public function getBaseUnit(string $category): ?Unit
    {
        return Unit::where('category', $category)
            ->where('is_base', true)
            ->first();
    }

    /**
     * Obtiene todas las unidades de una categoría
     * 
     * @param string $category Categoría (count o volume)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnitsByCategory(string $category)
    {
        return Unit::where('category', $category)
            ->where('state', 1)
            ->orderBy('name')
            ->get();
    }
}
