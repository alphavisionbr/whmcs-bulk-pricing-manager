<?php

namespace Alphavision\BulkPricing;

final class PriceCalculator
{
    const SYNC_CATALOG = 'sync_catalog';
    const INCREASE_PERCENT = 'increase_percent';
    const DECREASE_PERCENT = 'decrease_percent';
    const INCREASE_FIXED = 'increase_fixed';
    const DECREASE_FIXED = 'decrease_fixed';
    const ABSOLUTE = 'absolute';

    public static function operations()
    {
        return [
            self::SYNC_CATALOG => 'Sincronizar com o catálogo',
            self::INCREASE_PERCENT => 'Aumentar por percentual',
            self::DECREASE_PERCENT => 'Reduzir por percentual',
            self::INCREASE_FIXED => 'Aumentar por valor fixo',
            self::DECREASE_FIXED => 'Reduzir por valor fixo',
            self::ABSOLUTE => 'Definir novo valor absoluto',
        ];
    }

    public static function calculate($current, $catalog, $operation, $operand)
    {
        $current = round((float) $current, 4);
        $operand = $operand === null ? null : (float) $operand;

        switch ($operation) {
            case self::SYNC_CATALOG:
                if ($catalog === null || (float) $catalog < 0) {
                    throw new \InvalidArgumentException('Preço de catálogo indisponível para o ciclo e moeda deste registro.');
                }
                $newValue = (float) $catalog;
                break;

            case self::INCREASE_PERCENT:
                self::requireNonNegativeOperand($operand);
                $newValue = $current * (1 + ($operand / 100));
                break;

            case self::DECREASE_PERCENT:
                self::requireNonNegativeOperand($operand);
                $newValue = $current * (1 - ($operand / 100));
                break;

            case self::INCREASE_FIXED:
                self::requireNonNegativeOperand($operand);
                $newValue = $current + $operand;
                break;

            case self::DECREASE_FIXED:
                self::requireNonNegativeOperand($operand);
                $newValue = $current - $operand;
                break;

            case self::ABSOLUTE:
                self::requireNonNegativeOperand($operand);
                $newValue = $operand;
                break;

            default:
                throw new \InvalidArgumentException('Operação de preço inválida.');
        }

        $newValue = round($newValue, 2);
        if ($newValue < 0) {
            throw new \InvalidArgumentException('A operação produziria um preço negativo.');
        }

        return $newValue;
    }

    private static function requireNonNegativeOperand($operand)
    {
        if ($operand === null || !is_finite($operand) || $operand < 0) {
            throw new \InvalidArgumentException('O valor da operação deve ser maior ou igual a zero.');
        }
    }
}

