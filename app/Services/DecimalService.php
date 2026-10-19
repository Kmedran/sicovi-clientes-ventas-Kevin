<?php

namespace App\Services;

use InvalidArgumentException;

/** Exact, nonnegative decimal arithmetic. All public values are decimal strings. */
class DecimalService
{
    public function validate(string $value, int $precision, int $scale, bool $positive = false): string
    {
        if ($precision < 1 || $scale < 0 || $scale > $precision) {
            throw new InvalidArgumentException('La precisión y la escala decimal no son válidas.');
        }
        if (! preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/D', $value)) {
            throw new InvalidArgumentException('Ingrese un decimal no negativo con punto y sin separadores, espacios ni exponentes.');
        }

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0');
        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException("Se permiten como máximo {$scale} decimales; el valor no se redondea automáticamente.");
        }
        if (strlen($integer) > $precision - $scale) {
            throw new InvalidArgumentException("El valor excede el rango NUMERIC({$precision},{$scale}).");
        }
        if ($positive && bccomp($value, '0', $scale) <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor que cero.');
        }

        return bcadd($value, '0', $scale);
    }

    /** Quantities always occupy NUMERIC(14,3); unit precision additionally applies. */
    public function quantity(string $value, int $places, bool $positive = false): string
    {
        if ($places < 0 || $places > 3) {
            throw new InvalidArgumentException('La unidad debe permitir entre cero y tres decimales.');
        }
        $normalized = $this->validate($value, 14, 3, $positive);
        if (bccomp($normalized, bcadd($normalized, '0', $places), 3) !== 0) {
            throw new InvalidArgumentException("La cantidad no es compatible con la unidad de {$places} decimales.");
        }

        return $normalized;
    }

    public function money(string $value): string
    {
        return $this->validate($value, 14, 2);
    }

    /** Round a positive quantity times a nonnegative price half-up, exactly once. */
    public function lineTotal(string $quantity, string $price): string
    {
        $quantity = $this->validate($quantity, 14, 3, true);
        $price = $this->money($price);
        $raw = bcmul($quantity, $price, 5);
        $rounded = bcadd(bcadd($raw, '0.005', 5), '0', 2);

        return $this->validate($rounded, 16, 2);
    }

    /** @param list<string> $lines Already rounded line amounts, never browser totals. */
    public function total(array $lines): string
    {
        $total = '0.00';
        foreach ($lines as $line) {
            if (! is_string($line)) {
                throw new InvalidArgumentException('Los importes deben recibirse como cadenas decimales.');
            }
            $line = $this->validate($line, 16, 2);
            $total = $this->validate(bcadd($total, $line, 2), 16, 2);
        }

        return $total;
    }

    public function formatMoney(string $value): string
    {
        $value = $this->validate($value, 16, 2);
        [$integer, $fraction] = explode('.', $value, 2);
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer);

        return 'Q '.$integer.'.'.$fraction;
    }
}
