<?php

namespace Alphavision\BulkPricing;

use WHMCS\Database\Capsule;

final class AuditRepository
{
    public function createBatch(array $data)
    {
        return (int) Capsule::table('mod_av_bulkpricing_batches')->insertGetId([
            'operation_uuid' => Support::uuid(),
            'admin_id' => (int) $data['admin_id'],
            'entity_type' => (string) $data['entity_type'],
            'operation_type' => (string) $data['operation_type'],
            'operation_value' => $data['operation_value'],
            'filters_json' => json_encode($data['filters'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'item_count' => (int) $data['item_count'],
            'total_before' => 0,
            'total_after' => 0,
            'currency' => $data['currency'] ?? null,
            'status' => 'processing',
            'reversal_of_batch_id' => $data['reversal_of_batch_id'] ?? null,
            'created_at' => Support::now(),
            'completed_at' => null,
        ]);
    }

    public function addItem($batchId, array $item)
    {
        $context = $item['context_json'] ?? null;
        if (is_array($context)) {
            $context = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        Capsule::table('mod_av_bulkpricing_items')->insert([
            'batch_id' => (int) $batchId,
            'entity_type' => (string) $item['entity_type'],
            'entity_id' => (int) $item['entity_id'],
            'client_id' => isset($item['client_id']) ? (int) $item['client_id'] : null,
            'value_before' => (float) $item['value_before'],
            'value_after' => (float) $item['value_after'],
            'catalog_value' => $item['catalog_value'] === null ? null : (float) $item['catalog_value'],
            'currency' => $item['currency'] ?: null,
            'result' => (string) $item['result'],
            'error_message' => $item['error_message'] ?: null,
            'entity_label' => !empty($item['entity_label']) ? (string) $item['entity_label'] : null,
            'client_name' => !empty($item['client_name']) ? (string) $item['client_name'] : null,
            'context_json' => $context ?: null,
            'created_at' => Support::now(),
        ]);
    }

    public function finishBatch($batchId, $success, $failed, $totalBefore, $totalAfter)
    {
        if ($success > 0 && $failed === 0) {
            $status = 'completed';
        } elseif ($success > 0) {
            $status = 'partial';
        } else {
            $status = 'failed';
        }

        Capsule::table('mod_av_bulkpricing_batches')->where('id', (int) $batchId)->update([
            'status' => $status,
            'total_before' => round((float) $totalBefore, 4),
            'total_after' => round((float) $totalAfter, 4),
            'completed_at' => Support::now(),
        ]);

        return $status;
    }

    public function history($limit = 100)
    {
        $batches = Capsule::table('mod_av_bulkpricing_batches as b')
            ->leftJoin('tbladmins as a', 'a.id', '=', 'b.admin_id')
            ->orderBy('b.id', 'desc')->limit((int) $limit)->get([
                'b.*', 'a.firstname as admin_firstname', 'a.lastname as admin_lastname',
                'a.username as admin_username',
            ]);

        if (count($batches) < 1) {
            return $batches;
        }

        $batchIds = [];
        foreach ($batches as $batch) {
            $batchIds[] = (int) $batch->id;
            $batch->success_count = 0;
            $batch->skipped_count = 0;
        }

        $counts = Capsule::table('mod_av_bulkpricing_items')
            ->whereIn('batch_id', $batchIds)
            ->select('batch_id', 'result', Capsule::raw('COUNT(*) as total'))
            ->groupBy('batch_id', 'result')
            ->get();

        $byId = [];
        foreach ($batches as $batch) {
            $byId[(int) $batch->id] = $batch;
        }
        foreach ($counts as $count) {
            $property = $count->result === 'success' ? 'success_count' : 'skipped_count';
            $byId[(int) $count->batch_id]->{$property} += (int) $count->total;
        }

        return $batches;
    }

    public function batch($batchId)
    {
        return Capsule::table('mod_av_bulkpricing_batches as b')
            ->leftJoin('tbladmins as a', 'a.id', '=', 'b.admin_id')
            ->where('b.id', (int) $batchId)->first([
                'b.*', 'a.firstname as admin_firstname', 'a.lastname as admin_lastname',
                'a.username as admin_username',
            ]);
    }

    public function items($batchId)
    {
        return Capsule::table('mod_av_bulkpricing_items')
            ->where('batch_id', (int) $batchId)->orderBy('id')->get();
    }

    public function successfulItems($batchId)
    {
        return Capsule::table('mod_av_bulkpricing_items')
            ->where('batch_id', (int) $batchId)
            ->where('result', 'success')->orderBy('id')->get();
    }

    public function hasCompletedReversal($batchId)
    {
        return Capsule::table('mod_av_bulkpricing_batches')
            ->where('reversal_of_batch_id', (int) $batchId)
            ->whereIn('status', ['completed', 'partial'])
            ->exists();
    }

    public function hasLaterSuccessfulChange($entityType, $entityId, $afterBatchId)
    {
        return Capsule::table('mod_av_bulkpricing_items as i')
            ->join('mod_av_bulkpricing_batches as b', 'b.id', '=', 'i.batch_id')
            ->where('i.entity_type', (string) $entityType)
            ->where('i.entity_id', (int) $entityId)
            ->where('i.result', 'success')
            ->where('b.id', '>', (int) $afterBatchId)
            ->exists();
    }
}
