<?php

namespace Alphavision\BulkPricing;

use WHMCS\Database\Capsule;

final class PricingService
{
    private $entities;
    private $audit;
    private $batchSize;

    public function __construct(EntityRepository $entities, AuditRepository $audit, $batchSize)
    {
        $this->entities = $entities;
        $this->audit = $audit;
        $this->batchSize = max(1, min(200, (int) $batchSize));
    }

    public function simulate($entityType, array $ids, $operation, $operand, array $filters)
    {
        if (!isset(PriceCalculator::operations()[$operation])) {
            throw new \InvalidArgumentException('Selecione uma operação válida.');
        }

        if ($operation !== PriceCalculator::SYNC_CATALOG && $operand === null) {
            throw new \InvalidArgumentException('Informe o valor da operação.');
        }

        $rows = $this->entities->findByIds($entityType, $ids);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['entity_id']] = $row;
        }

        $items = [];
        $totalBefore = 0;
        $totalAfter = 0;
        $valid = 0;
        $invalid = 0;
        $currencies = [];

        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                $items[] = [
                    'entity_type' => $entityType,
                    'entity_id' => $id,
                    'client_id' => null,
                    'client_name' => '',
                    'item_name' => 'Registro não localizado',
                    'identifier' => '',
                    'cycle' => '',
                    'current_value' => 0,
                    'catalog_value' => null,
                    'new_value' => 0,
                    'currency' => '',
                    'prefix' => '',
                    'suffix' => '',
                    'error' => 'O registro não existe ou não está mais disponível.',
                ];
                $invalid++;
                continue;
            }

            $row = $byId[$id];
            $error = null;
            $newValue = $row['current_value'];
            try {
                $newValue = PriceCalculator::calculate(
                    $row['current_value'],
                    $row['catalog_value'],
                    $operation,
                    $operand
                );
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }

            $row['new_value'] = $newValue;
            $row['error'] = $error;
            $items[] = $row;

            if ($error === null) {
                $valid++;
                $totalBefore += $row['current_value'];
                $totalAfter += $newValue;
                if ($row['currency'] !== '') {
                    $currencies[$row['currency']] = true;
                }
            } else {
                $invalid++;
            }
        }

        $simulation = [
            'admin_id' => Support::adminId(),
            'entity_type' => $entityType,
            'operation' => $operation,
            'operand' => $operand,
            'filters' => $filters,
            'items' => $items,
            'valid_count' => $valid,
            'invalid_count' => $invalid,
            'total_before' => round($totalBefore, 2),
            'total_after' => round($totalAfter, 2),
            'currencies' => array_keys($currencies),
            'created_timestamp' => time(),
        ];

        $simulation['id'] = Support::sessionPutSimulation($simulation);
        return $simulation;
    }

    public function execute(array $simulation)
    {
        if ((int) $simulation['admin_id'] !== Support::adminId()) {
            throw new \RuntimeException('A simulação pertence a outra sessão administrativa.');
        }
        if ((int) $simulation['valid_count'] < 1) {
            throw new \RuntimeException('A simulação não possui registros válidos para executar.');
        }

        $batchId = $this->audit->createBatch([
            'admin_id' => Support::adminId(),
            'entity_type' => $simulation['entity_type'],
            'operation_type' => $simulation['operation'],
            'operation_value' => $simulation['operand'],
            'filters' => $simulation['filters'],
            'item_count' => count($simulation['items']),
            'currency' => count($simulation['currencies']) === 1 ? $simulation['currencies'][0] : 'Múltiplas',
        ]);

        $success = 0;
        $failed = 0;
        $totalBefore = 0;
        $totalAfter = 0;

        foreach (array_chunk($simulation['items'], $this->batchSize) as $chunk) {
            $ids = array_map(function ($item) {
                return (int) $item['entity_id'];
            }, $chunk);
            $currentRows = $this->entities->findByIds($simulation['entity_type'], $ids);
            $currentById = [];
            foreach ($currentRows as $currentRow) {
                $currentById[(int) $currentRow['entity_id']] = $currentRow;
            }

            foreach ($chunk as $item) {
                $result = $this->executeItem($batchId, $simulation, $item, $currentById);
                if ($result['success']) {
                    $success++;
                    $totalBefore += $result['before'];
                    $totalAfter += $result['after'];
                } else {
                    $failed++;
                }
            }
        }

        $status = $this->audit->finishBatch($batchId, $success, $failed, $totalBefore, $totalAfter);
        return ['batch_id' => $batchId, 'success' => $success, 'failed' => $failed, 'status' => $status];
    }

    public function reverse($batchId)
    {
        $original = $this->audit->batch($batchId);
        if (!$original) {
            throw new \RuntimeException('Lote original não localizado.');
        }
        if ($original->operation_type === 'reversal') {
            throw new \RuntimeException('Um lote de reversão não pode ser revertido por esta rotina.');
        }
        if ($this->audit->hasCompletedReversal($batchId)) {
            throw new \RuntimeException('Este lote já possui uma reversão concluída ou parcial.');
        }

        $items = $this->audit->successfulItems($batchId);
        if (count($items) < 1) {
            throw new \RuntimeException('O lote não possui itens bem-sucedidos para reverter.');
        }

        $reversalId = $this->audit->createBatch([
            'admin_id' => Support::adminId(),
            'entity_type' => $original->entity_type,
            'operation_type' => 'reversal',
            'operation_value' => null,
            'filters' => ['original_batch_id' => (int) $batchId],
            'item_count' => count($items),
            'currency' => $original->currency,
            'reversal_of_batch_id' => (int) $batchId,
        ]);

        $success = 0;
        $failed = 0;
        $totalBefore = 0;
        $totalAfter = 0;

        foreach (array_chunk($items->all(), $this->batchSize) as $chunk) {
            $ids = array_map(function ($item) {
                return (int) $item->entity_id;
            }, $chunk);
            $currentRows = $this->entities->findByIds($original->entity_type, $ids);
            $currentById = [];
            foreach ($currentRows as $row) {
                $currentById[(int) $row['entity_id']] = $row;
            }

            foreach ($chunk as $item) {
                $current = $currentById[(int) $item->entity_id] ?? null;
                $error = null;
                if (!$current) {
                    $error = 'Registro não localizado.';
                } elseif ($this->audit->hasLaterSuccessfulChange($original->entity_type, $item->entity_id, $batchId)) {
                    $error = 'O registro sofreu uma alteração posterior ao lote original.';
                } elseif (abs((float) $current['current_value'] - (float) $item->value_after) > 0.0001) {
                    $error = 'O preço atual não corresponde ao valor deixado pelo lote original.';
                }

                try {
                    if ($error !== null) {
                        throw new \RuntimeException($error);
                    }

                    Capsule::connection()->transaction(function () use ($original, $item, $current, $reversalId) {
                        $this->entities->updateAmount($original->entity_type, $item->entity_id, $item->value_before);
                        $this->audit->addItem($reversalId, [
                            'entity_type' => $original->entity_type,
                            'entity_id' => $item->entity_id,
                            'client_id' => $item->client_id,
                            'value_before' => $current['current_value'],
                            'value_after' => $item->value_before,
                            'catalog_value' => $current['catalog_value'],
                            'currency' => $current['currency'],
                            'result' => 'success',
                            'error_message' => null,
                            'entity_label' => $item->entity_label ?: $this->entityLabel($current),
                            'client_name' => $item->client_name ?: $current['client_name'],
                            'context_json' => $item->context_json ?: $this->context($current),
                        ]);
                    });
                    $success++;
                    $totalBefore += (float) $current['current_value'];
                    $totalAfter += (float) $item->value_before;
                } catch (\Throwable $exception) {
                    $failed++;
                    $this->audit->addItem($reversalId, [
                        'entity_type' => $original->entity_type,
                        'entity_id' => $item->entity_id,
                        'client_id' => $item->client_id,
                        'value_before' => $current ? $current['current_value'] : $item->value_after,
                        'value_after' => $item->value_before,
                        'catalog_value' => $current ? $current['catalog_value'] : null,
                        'currency' => $current ? $current['currency'] : $item->currency,
                        'result' => 'skipped',
                        'error_message' => $exception->getMessage(),
                        'entity_label' => $item->entity_label ?: ($current ? $this->entityLabel($current) : 'Registro #' . $item->entity_id),
                        'client_name' => $item->client_name ?: ($current ? $current['client_name'] : ''),
                        'context_json' => $item->context_json ?: ($current ? $this->context($current) : null),
                    ]);
                }
            }
        }

        $status = $this->audit->finishBatch($reversalId, $success, $failed, $totalBefore, $totalAfter);
        return ['batch_id' => $reversalId, 'success' => $success, 'failed' => $failed, 'status' => $status];
    }

    private function executeItem($batchId, array $simulation, array $item, array $currentById)
    {
        $current = $currentById[(int) $item['entity_id']] ?? null;
        $error = $item['error'];

        if ($error === null && !$current) {
            $error = 'Registro não localizado no momento da confirmação.';
        }
        if ($error === null && abs((float) $current['current_value'] - (float) $item['current_value']) > 0.0001) {
            $error = 'O preço foi alterado depois da simulação.';
        }
        if (
            $error === null
            && $simulation['operation'] === PriceCalculator::SYNC_CATALOG
            && !$this->sameNullableDecimal($current['catalog_value'], $item['catalog_value'])
        ) {
            $error = 'O preço de catálogo mudou depois da simulação.';
        }

        try {
            if ($error !== null) {
                throw new \RuntimeException($error);
            }

            $recalculated = PriceCalculator::calculate(
                $current['current_value'],
                $current['catalog_value'],
                $simulation['operation'],
                $simulation['operand']
            );
            if (abs($recalculated - (float) $item['new_value']) > 0.0001) {
                throw new \RuntimeException('O resultado recalculado diverge da simulação.');
            }

            Capsule::connection()->transaction(function () use ($batchId, $simulation, $item, $current, $recalculated) {
                $this->entities->updateAmount($simulation['entity_type'], $item['entity_id'], $recalculated);
                $this->audit->addItem($batchId, [
                    'entity_type' => $simulation['entity_type'],
                    'entity_id' => $item['entity_id'],
                    'client_id' => $current['client_id'],
                    'value_before' => $current['current_value'],
                    'value_after' => $recalculated,
                    'catalog_value' => $current['catalog_value'],
                    'currency' => $current['currency'],
                    'result' => 'success',
                    'error_message' => null,
                    'entity_label' => $this->entityLabel($current),
                    'client_name' => $current['client_name'],
                    'context_json' => $this->context($current),
                ]);
            });

            return ['success' => true, 'before' => $current['current_value'], 'after' => $recalculated];
        } catch (\Throwable $exception) {
            $this->audit->addItem($batchId, [
                'entity_type' => $simulation['entity_type'],
                'entity_id' => $item['entity_id'],
                'client_id' => $current ? $current['client_id'] : $item['client_id'],
                'value_before' => $current ? $current['current_value'] : $item['current_value'],
                'value_after' => $item['new_value'],
                'catalog_value' => $current ? $current['catalog_value'] : $item['catalog_value'],
                'currency' => $current ? $current['currency'] : $item['currency'],
                'result' => 'skipped',
                'error_message' => $exception->getMessage(),
                'entity_label' => $this->entityLabel($current ?: $item),
                'client_name' => $current ? $current['client_name'] : $item['client_name'],
                'context_json' => $this->context($current ?: $item),
            ]);

            return ['success' => false, 'before' => 0, 'after' => 0];
        }
    }

    private function sameNullableDecimal($a, $b)
    {
        if ($a === null || $b === null) {
            return $a === null && $b === null;
        }
        return abs((float) $a - (float) $b) <= 0.0001;
    }

    private function entityLabel(array $row)
    {
        if (($row['entity_type'] ?? '') === 'domain') {
            return (string) ($row['identifier'] ?: 'Domínio #' . $row['entity_id']);
        }

        return (string) ($row['item_name'] ?: 'Registro #' . $row['entity_id']);
    }

    private function context(array $row)
    {
        return [
            'item_name' => (string) ($row['item_name'] ?? ''),
            'identifier' => (string) ($row['identifier'] ?? ''),
            'cycle' => (string) ($row['cycle'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'registrar' => (string) ($row['registrar'] ?? ''),
        ];
    }
}
