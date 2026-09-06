<?php

namespace Alphavision\BulkPricing;

final class AdminController
{
    private $vars;
    private $moduleLink;
    private $entities;
    private $audit;
    private $pricing;
    private $view;
    private $pageSize;

    public function __construct(array $vars)
    {
        $this->vars = $vars;
        $this->moduleLink = (string) $vars['modulelink'];
        $this->pageSize = max(25, min(200, (int) ($vars['records_per_page'] ?? 50)));
        $batchSize = max(25, min(200, (int) ($vars['batch_size'] ?? 50)));
        $this->entities = new EntityRepository();
        $this->audit = new AuditRepository();
        $this->pricing = new PricingService($this->entities, $this->audit, $batchSize);
        $this->view = new AdminView($this->moduleLink);
    }

    public function handle()
    {
        Support::adminId();
        $notice = null;

        try {
            if (Support::requestMethod() === 'POST') {
                Support::validateCsrf($_POST['csrf_token'] ?? '');
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'simulate') {
                    return $this->simulate();
                }
                if ($action === 'execute') {
                    $simulation = Support::sessionPullSimulation($_POST['simulation_id'] ?? '');
                    $result = $this->pricing->execute($simulation);
                    $notice = $this->resultNotice('execução', $result);
                    return $this->batchDetail($result['batch_id'], $notice);
                }
                if ($action === 'reverse') {
                    $batchId = (int) ($_POST['batch_id'] ?? 0);
                    $result = $this->pricing->reverse($batchId);
                    $notice = $this->resultNotice('reversão', $result);
                    return $this->batchDetail($result['batch_id'], $notice);
                }

                throw new \InvalidArgumentException('Ação administrativa inválida.');
            }
        } catch (\Throwable $exception) {
            $notice = ['type' => 'danger', 'message' => $exception->getMessage()];
            $this->logFailure($exception);
        }

        $page = (string) ($_GET['page'] ?? 'dashboard');
        if (in_array($page, ['service', 'domain', 'addon'], true)) {
            return $this->entityPage($page, $notice);
        }
        if ($page === 'history') {
            return $this->view->history($this->audit->history(), Support::csrfToken(), $notice);
        }
        if ($page === 'batch') {
            return $this->batchDetail((int) ($_GET['id'] ?? 0), $notice);
        }
        if ($page === 'settings') {
            return $this->view->settings($this->vars, $notice);
        }

        return $this->view->dashboard($this->entities->dashboardMetrics(), $notice);
    }

    private function entityPage($entityType, $notice)
    {
        $filters = $this->filters($entityType);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $offset = ($page - 1) * $this->pageSize;
        $listing = $this->entities->listing($entityType, $filters, $this->pageSize, $offset);
        $choices = $this->entities->choices($entityType);

        return $this->view->entityList(
            $entityType,
            $listing,
            $choices,
            $filters,
            $page,
            $this->pageSize,
            Support::csrfToken(),
            $notice
        );
    }

    private function simulate()
    {
        $entityType = (string) ($_POST['entity_type'] ?? '');
        if (!in_array($entityType, ['service', 'domain', 'addon'], true)) {
            throw new \InvalidArgumentException('Tipo de registro inválido.');
        }

        $ids = Support::ids($_POST['selected'] ?? []);
        $operation = (string) ($_POST['operation'] ?? '');
        $operand = $operation === PriceCalculator::SYNC_CATALOG
            ? null
            : Support::decimal($_POST['operation_value'] ?? '');
        $filters = json_decode((string) ($_POST['filters_json'] ?? '{}'), true);
        if (!is_array($filters)) {
            $filters = [];
        }

        $simulation = $this->pricing->simulate($entityType, $ids, $operation, $operand, $filters);
        return $this->view->simulation($simulation, Support::csrfToken());
    }

    private function batchDetail($batchId, $notice)
    {
        $batch = $this->audit->batch($batchId);
        if (!$batch) {
            return $this->view->message('Lote não localizado.', 'danger');
        }

        $items = $this->audit->items($batchId);
        $liveContext = [];
        $ids = [];
        foreach ($items as $item) {
            $ids[] = (int) $item->entity_id;
        }
        if ($ids) {
            foreach ($this->entities->findByIds($batch->entity_type, array_values(array_unique($ids))) as $row) {
                $liveContext[(int) $row['entity_id']] = $row;
            }
        }

        return $this->view->batch(
            $batch,
            $items,
            $liveContext,
            $this->audit->hasCompletedReversal($batchId),
            Support::csrfToken(),
            $notice
        );
    }

    private function filters($entityType)
    {
        $allowed = [
            'service' => ['group_id', 'product_id', 'client_id', 'currency_id', 'cycle', 'status', 'query', 'min_price', 'max_price'],
            'domain' => ['tld', 'registrar', 'period', 'client_id', 'currency_id', 'status', 'query', 'min_price', 'max_price', 'due_from', 'due_to'],
            'addon' => ['addon_id', 'client_id', 'currency_id', 'cycle', 'status', 'query', 'min_price', 'max_price'],
        ];
        $filters = [];
        foreach ($allowed[$entityType] as $key) {
            if (isset($_GET[$key]) && trim((string) $_GET[$key]) !== '') {
                $filters[$key] = trim((string) $_GET[$key]);
            }
        }
        return $filters;
    }

    private function resultNotice($label, array $result)
    {
        $type = $result['failed'] > 0 ? 'warning' : 'success';
        return [
            'type' => $type,
            'message' => ucfirst($label) . ' concluída. ' . $result['success'] . ' registro(s) alterado(s) e '
                . $result['failed'] . ' registro(s) ignorado(s).',
        ];
    }

    private function logFailure(\Throwable $exception)
    {
        if (!function_exists('logModuleCall')) {
            return;
        }
        logModuleCall(
            'alphavision_bulk_pricing_manager',
            'admin-action',
            ['action' => $_POST['action'] ?? '', 'page' => $_GET['page'] ?? 'dashboard'],
            $exception->getMessage(),
            $exception->getTraceAsString()
        );
    }
}
