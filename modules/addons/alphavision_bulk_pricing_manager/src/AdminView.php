<?php

namespace Alphavision\BulkPricing;

final class AdminView
{
    private $moduleLink;

    public function __construct($moduleLink)
    {
        $this->moduleLink = $moduleLink;
    }

    public function dashboard(array $metrics, $notice = null)
    {
        $last = $metrics['last_batch'];
        $lastText = $last ? '#' . (int) $last->id . ' em ' . $this->dateTime($last->created_at) : 'Nenhum lote';
        $content = $this->notice($notice)
            . '<div class="av-bp-cards">'
            . $this->card('Serviços monitorados', number_format($metrics['service_count'], 0, ',', '.'), 'fa-server')
            . $this->card('Domínios monitorados', number_format($metrics['domain_count'], 0, ',', '.'), 'fa-globe')
            . $this->card('Addons monitorados', number_format($metrics['addon_count'], 0, ',', '.'), 'fa-puzzle-piece')
            . $this->card('Alterações em 30 dias', number_format($metrics['items_30_days'], 0, ',', '.'), 'fa-history')
            . '</div>'
            . '<div class="av-bp-panel"><div class="av-bp-panel-title"><h3>Visão operacional</h3></div>'
            . '<div class="av-bp-summary-grid">'
            . '<div><span>Última atualização</span><strong>' . Support::e($lastText) . '</strong></div>'
            . '<div><span>Lotes nos últimos 30 dias</span><strong>' . (int) $metrics['batches_30_days'] . '</strong></div>'
            . '<div><span>Política de faturas</span><strong>Faturas emitidas não são alteradas</strong></div>'
            . '</div></div>'
            . '<div class="alert alert-info av-bp-info"><strong>Fluxo seguro:</strong> filtre os registros, selecione apenas os desejados, simule e confirme. Toda execução gera auditoria por item.</div>';

        return $this->layout('Visão geral', 'Controle seletivo dos preços contratados no WHMCS.', $content, 'dashboard');
    }

    public function entityList($type, array $listing, array $choices, array $filters, $page, $pageSize, $csrf, $notice = null)
    {
        $meta = $this->entityMeta($type);
        $content = $this->notice($notice)
            . $this->filters($type, $choices, $filters)
            . '<form method="post" action="' . Support::e($this->moduleLink . '&page=' . $type) . '" id="av-bp-operation-form">'
            . '<input type="hidden" name="csrf_token" value="' . Support::e($csrf) . '">'
            . '<input type="hidden" name="action" value="simulate">'
            . '<input type="hidden" name="entity_type" value="' . Support::e($type) . '">'
            . '<input type="hidden" name="filters_json" value="' . Support::e(json_encode($filters, JSON_UNESCAPED_UNICODE)) . '">'
            . '<div class="av-bp-panel"><div class="av-bp-panel-title av-bp-panel-title-row">'
            . '<div><h3>Registros encontrados</h3><p>' . number_format($listing['total'], 0, ',', '.') . ' resultado(s)</p></div>'
            . '<label class="av-bp-select-all"><input type="checkbox" id="av-bp-select-all"> Selecionar página</label>'
            . '</div>'
            . $this->entityTable($type, $listing['rows'])
            . $this->pagination($type, $filters, $listing['total'], $page, $pageSize)
            . '</div>'
            . '<div class="av-bp-operation-bar">'
            . '<div><label for="av-bp-operation">Operação</label><select class="form-control" name="operation" id="av-bp-operation" required>'
            . '<option value="">Selecione</option>' . $this->options(PriceCalculator::operations(), '') . '</select></div>'
            . '<div id="av-bp-operation-value-wrap"><label for="av-bp-operation-value">Valor</label><input class="form-control" type="text" inputmode="decimal" name="operation_value" id="av-bp-operation-value" placeholder="0,00"></div>'
            . '<div class="av-bp-operation-action"><button type="submit" class="btn btn-primary"><i class="fas fa-calculator"></i> Simular alteração</button></div>'
            . '</div></form>';

        return $this->layout($meta['plural'], $meta['description'], $content, $type);
    }

    public function simulation(array $simulation, $csrf)
    {
        $difference = $simulation['total_after'] - $simulation['total_before'];
        $percent = $simulation['total_before'] != 0
            ? ($difference / $simulation['total_before']) * 100
            : 0;
        $currency = count($simulation['currencies']) === 1 ? $simulation['currencies'][0] : 'Múltiplas moedas';

        $rows = '';
        foreach ($simulation['items'] as $item) {
            $valid = $item['error'] === null;
            $rows .= '<tr class="' . ($valid ? '' : 'av-bp-row-error') . '">'
                . '<td>' . $this->recordIdentity($item) . '</td>'
                . '<td>' . Support::e($item['client_name']) . '</td>'
                . '<td>' . Support::e($item['cycle']) . '</td>'
                . '<td class="text-right">' . Support::e(Support::money($item['current_value'], $item['prefix'], $item['suffix'])) . '</td>'
                . '<td class="text-right"><strong>' . Support::e(Support::money($item['new_value'], $item['prefix'], $item['suffix'])) . '</strong></td>'
                . '<td>' . ($valid ? '<span class="label label-success">Pronto</span>' : '<span class="label label-warning">Ignorado</span><small>' . Support::e($item['error']) . '</small>') . '</td>'
                . '</tr>';
        }

        $content = '<div class="alert alert-warning"><strong>Esta é apenas uma simulação.</strong> Nenhum preço foi alterado. Os dados serão revalidados no momento da confirmação.</div>'
            . '<div class="av-bp-cards">'
            . $this->card('Registros válidos', $simulation['valid_count'], 'fa-check-circle')
            . $this->card('Registros ignorados', $simulation['invalid_count'], 'fa-exclamation-triangle')
            . $this->card('Total antes', Support::money($simulation['total_before']) . ' ' . $currency, 'fa-arrow-left')
            . $this->card('Total depois', Support::money($simulation['total_after']) . ' ' . $currency, 'fa-arrow-right')
            . '</div>'
            . '<div class="av-bp-panel"><div class="av-bp-panel-title"><h3>Antes e depois</h3><p>Diferença total: ' . Support::e(Support::money($difference)) . ' (' . number_format($percent, 2, ',', '.') . '%)</p></div>'
            . '<div class="table-responsive"><table class="table av-bp-table"><thead><tr><th>Registro</th><th>Cliente</th><th>Ciclo</th><th class="text-right">Antes</th><th class="text-right">Depois</th><th>Validação</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div></div>'
            . '<div class="av-bp-actions">'
            . '<a class="btn btn-default" href="' . Support::e($this->moduleLink . '&page=' . $simulation['entity_type']) . '">Cancelar e voltar</a>'
            . ($simulation['valid_count'] > 0 ? '<button class="btn btn-danger" data-toggle="modal" data-target="#av-bp-confirm-modal"><i class="fas fa-check"></i> Confirmar alteração</button>' : '')
            . '</div>'
            . $this->confirmationModal($simulation, $csrf);

        return $this->layout('Simulação obrigatória', 'Revise cada registro antes de confirmar a alteração.', $content, $simulation['entity_type']);
    }

    public function history($batches, $csrf, $notice = null)
    {
        $rows = '';
        foreach ($batches as $batch) {
            $admin = trim((string) $batch->admin_firstname . ' ' . (string) $batch->admin_lastname);
            if ($admin === '') {
                $admin = (string) $batch->admin_username;
            }
            $difference = (float) $batch->total_after - (float) $batch->total_before;
            $operationValue = $batch->operation_value !== null && $batch->operation_type !== 'reversal'
                ? '<small>Parâmetro: ' . Support::e($this->operationValue($batch)) . '</small>'
                : '';
            $filterCount = count($this->decodedFilters($batch->filters_json));
            $scopeNote = $filterCount > 0
                ? $filterCount . ' filtro(s) aplicado(s) antes da seleção'
                : 'Seleção manual sem filtros adicionais';

            $rows .= '<tr><td><a class="av-history-link" href="' . Support::e($this->moduleLink . '&page=batch&id=' . $batch->id) . '"><strong>Lote #' . (int) $batch->id . '</strong><small>' . Support::e($this->dateTime($batch->created_at)) . '</small></a></td>'
                . '<td><strong>' . Support::e($this->operationLabel($batch->operation_type)) . '</strong>' . $operationValue . '</td>'
                . '<td><strong>' . Support::e($this->entityMeta($batch->entity_type)['singular']) . ': ' . (int) $batch->item_count . ' selecionado(s)</strong><small>' . (int) $batch->success_count . ' alterado(s), ' . (int) $batch->skipped_count . ' ignorado(s)</small><small>' . Support::e($scopeNote) . '</small></td>'
                . '<td><strong>' . Support::e($this->batchMoney($batch->total_before, $batch->currency)) . ' → ' . Support::e($this->batchMoney($batch->total_after, $batch->currency)) . '</strong><small>Diferença: ' . Support::e($this->signedMoney($difference, $batch->currency)) . '</small></td>'
                . '<td><strong>' . Support::e($admin ?: 'Administrador removido') . '</strong></td>'
                . '<td>' . $this->statusBadge($batch->status) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="av-bp-empty">Nenhuma operação registrada.</td></tr>';
        }

        $content = $this->notice($notice)
            . '<div class="av-bp-panel"><div class="av-bp-panel-title"><h3>Lotes de alteração</h3><p>Histórico imutável de execuções e reversões.</p></div>'
            . '<div class="table-responsive"><table class="table av-bp-table av-history-table"><thead><tr><th>Lote e data</th><th>Ação executada</th><th>Escopo</th><th>Impacto financeiro</th><th>Administrador</th><th>Status</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div></div>';
        return $this->layout('Histórico', 'Auditoria completa das alterações de preço.', $content, 'history');
    }

    public function batch($batch, $items, array $liveContext, $hasReversal, $csrf, $notice = null)
    {
        $rows = '';
        $success = 0;
        $skipped = 0;
        foreach ($items as $item) {
            $item->result === 'success' ? $success++ : $skipped++;
            $context = $this->auditItemContext($item, $liveContext[(int) $item->entity_id] ?? null);
            $difference = (float) $item->value_after - (float) $item->value_before;
            $rows .= '<tr><td><strong>' . Support::e($context['label']) . '</strong><small>' . Support::e($this->entityMeta($item->entity_type)['singular']) . ' #' . (int) $item->entity_id . $this->contextSuffix($context) . '</small></td>'
                . '<td><strong>' . Support::e($context['client_name'] ?: 'Cliente não disponível') . '</strong>' . ($item->client_id ? '<small>Cliente #' . (int) $item->client_id . '</small>' : '') . '</td>'
                . '<td class="text-right">' . Support::e(number_format((float) $item->value_before, 2, ',', '.')) . '</td>'
                . '<td class="text-right">' . Support::e(number_format((float) $item->value_after, 2, ',', '.')) . '</td>'
                . '<td class="text-right">' . Support::e($this->signedMoney($difference, $item->currency)) . '</td>'
                . '<td>' . ($item->result === 'success' ? '<span class="label label-success">Alterado</span>' : '<span class="label label-warning">Ignorado</span>') . '</td>'
                . '<td>' . Support::e($item->error_message ?: '') . '</td></tr>';
        }

        $batchDifference = (float) $batch->total_after - (float) $batch->total_before;
        $filters = $this->filterSummary($batch->filters_json);
        $admin = trim((string) $batch->admin_firstname . ' ' . (string) $batch->admin_lastname);
        if ($admin === '') {
            $admin = (string) $batch->admin_username;
        }

        $content = $this->notice($notice)
            . '<div class="av-bp-cards">'
            . $this->card('Lote', '#' . (int) $batch->id, 'fa-layer-group')
            . $this->card('Alterados', $success, 'fa-check-circle')
            . $this->card('Ignorados', $skipped, 'fa-exclamation-triangle')
            . $this->card('Status', $this->statusText($batch->status), 'fa-info-circle')
            . '</div>'
            . '<div class="av-bp-panel"><div class="av-bp-panel-title"><h3>O que foi feito</h3><p>Resumo completo da decisão administrativa registrada neste lote.</p></div>'
            . '<div class="av-audit-summary">'
            . $this->auditSummaryItem('Ação', $this->operationLabel($batch->operation_type), $batch->operation_value !== null ? $this->operationValue($batch) : '')
            . $this->auditSummaryItem('Escopo', $this->entityMeta($batch->entity_type)['plural'], (int) $batch->item_count . ' registro(s) selecionado(s)')
            . $this->auditSummaryItem('Administrador', $admin ?: 'Administrador removido', 'ID administrativo ' . (int) $batch->admin_id)
            . $this->auditSummaryItem('Período da execução', $this->dateTime($batch->created_at), $batch->completed_at ? 'Concluído em ' . $this->dateTime($batch->completed_at) : 'Conclusão não registrada')
            . $this->auditSummaryItem('Valor recorrente total', $this->batchMoney($batch->total_before, $batch->currency) . ' → ' . $this->batchMoney($batch->total_after, $batch->currency), 'Diferença: ' . $this->signedMoney($batchDifference, $batch->currency))
            . $this->auditSummaryItem('Filtros utilizados', $filters['title'], $filters['detail'])
            . '</div></div>'
            . '<div class="av-bp-panel"><div class="av-bp-panel-title av-bp-panel-title-row"><div><h3>Itens do lote</h3><p>'
            . Support::e($this->operationLabel($batch->operation_type)) . ' em ' . Support::e($this->dateTime($batch->created_at)) . '</p></div>'
            . $this->reversalButton($batch, $hasReversal, $csrf)
            . '</div><div class="table-responsive"><table class="table av-bp-table av-audit-items"><thead><tr><th>Registro</th><th>Cliente</th><th class="text-right">Antes</th><th class="text-right">Depois</th><th class="text-right">Diferença</th><th>Resultado</th><th>Observação</th></tr></thead><tbody>'
            . $rows . '</tbody></table></div></div>';

        return $this->layout('Detalhes do lote #' . (int) $batch->id, 'Resultado individual e proteção de auditoria.', $content, 'history');
    }

    public function settings(array $vars, $notice = null)
    {
        $content = $this->notice($notice)
            . '<div class="av-bp-panel"><div class="av-bp-panel-title"><h3>Configuração operacional</h3><p>As configurações são administradas na página nativa de módulos addon do WHMCS.</p></div>'
            . '<div class="av-bp-summary-grid">'
            . '<div><span>Versão instalada</span><strong>' . Support::e(ALPHAVISION_BULK_PRICING_VERSION) . '</strong></div>'
            . '<div><span>Registros por página</span><strong>' . (int) ($vars['records_per_page'] ?? 50) . '</strong></div>'
            . '<div><span>Tamanho do lote interno</span><strong>' . (int) ($vars['batch_size'] ?? 50) . '</strong></div>'
            . '</div><div class="av-bp-panel-footer"><a class="btn btn-default" href="configaddonmods.php"><i class="fas fa-cog"></i> Abrir configuração de addons</a></div></div>'
            . '<div class="alert alert-info av-bp-info"><strong>Escopo da versão ' . Support::e(ALPHAVISION_BULK_PRICING_VERSION) . ':</strong> o módulo altera somente valores recorrentes futuros. Nenhuma fatura existente é modificada.</div>';
        return $this->layout('Configurações', 'Parâmetros de exibição e processamento.', $content, 'settings');
    }

    public function message($message, $type)
    {
        return $this->layout('Bulk Pricing Manager', '', '<div class="alert alert-' . Support::e($type) . '">' . Support::e($message) . '</div>', '');
    }

    private function layout($title, $description, $content, $active)
    {
        $nav = [
            'dashboard' => ['Visão geral', 'fa-chart-line'],
            'service' => ['Produtos e Serviços', 'fa-server'],
            'domain' => ['Domínios', 'fa-globe'],
            'addon' => ['Addons', 'fa-puzzle-piece'],
            'history' => ['Histórico', 'fa-history'],
            'settings' => ['Configurações', 'fa-cog'],
        ];
        $links = '';
        foreach ($nav as $key => $item) {
            $href = $key === 'dashboard' ? $this->moduleLink : $this->moduleLink . '&page=' . $key;
            $links .= '<a class="av-tab' . ($active === $key ? ' is-active' : '') . '" href="' . Support::e($href) . '"><i class="fas ' . $item[1] . '"></i> ' . Support::e($item[0]) . '</a>';
        }

        return '<link rel="stylesheet" href="../modules/addons/alphavision_bulk_pricing_manager/assets/css/admin.css?v=' . Support::e(ALPHAVISION_BULK_PRICING_VERSION) . '">'
            . '<div class="av-admin av-bp">'
            . '<header class="av-header av-bp-header"><div><p class="av-eyebrow">Alphavision WHMCS</p>'
            . '<h2>Bulk Pricing Manager <span>v' . Support::e(ALPHAVISION_BULK_PRICING_VERSION) . '</span></h2>'
            . '<p>Atualizações seletivas, simulação obrigatória e auditoria de preços em um único painel.</p></div>'
            . '<div class="av-header-status"><span class="av-dot"></span>Módulo ativo</div></header>'
            . '<nav class="av-tabs av-bp-nav" aria-label="Navegação do módulo">' . $links . '</nav>'
            . '<main class="av-bp-main"><div class="av-page-intro"><h3>' . Support::e($title) . '</h3><p>' . Support::e($description) . '</p></div>' . $content . $this->contributionCard() . '</main>'
            . '</div><script src="../modules/addons/alphavision_bulk_pricing_manager/assets/js/admin.js?v=' . Support::e(ALPHAVISION_BULK_PRICING_VERSION) . '"></script>';
    }

    private function contributionCard()
    {
        $projectUrl = 'https://alphavision.com.br/whmcs';
        $reportUrl = 'mailto:contato@alphavision.com.br?subject=Relato%20sobre%20o%20Alphavision%20WHMCS%20Bulk%20Pricing%20Manager';

        return '<section class="av-bp-panel av-bp-contribution"><div><p class="av-bp-contribution-kicker">Projeto gratuito</p>'
            . '<h3>Apoie o desenvolvimento</h3><p>Este módulo é disponibilizado gratuitamente pela Alphavision®. Contribuições ajudam a manter testes de compatibilidade, correções e novas melhorias.</p></div>'
            . '<div class="av-bp-contribution-actions">'
            . '<button type="button" class="btn btn-primary" disabled title="O canal de contribuição será disponibilizado em breve.">Fazer uma contribuição</button>'
            . '<a class="btn btn-default" target="_blank" rel="noopener" href="' . Support::e($projectUrl) . '">Página do projeto</a>'
            . '<a class="btn btn-default" href="' . Support::e($reportUrl) . '">Reportar um problema</a>'
            . '</div></section>';
    }

    private function filters($type, array $choices, array $filters)
    {
        $fields = '<div><label>Pesquisar</label><input class="form-control" type="text" name="query" value="' . Support::e($filters['query'] ?? '') . '" placeholder="Cliente, item, domínio ou ID"></div>';
        if ($type === 'service') {
            $fields .= $this->selectField('group_id', 'Grupo', $this->objectOptions($choices['groups'], 'id', 'name'), $filters);
            $fields .= $this->selectField('product_id', 'Produto', $this->objectOptions($choices['products'], 'id', 'name'), $filters);
            $fields .= $this->selectField('cycle', 'Ciclo', $this->cycles(), $filters);
            $fields .= $this->selectField('status', 'Status', $this->serviceStatuses(), $filters);
        } elseif ($type === 'domain') {
            $fields .= $this->selectField('tld', 'TLD', $this->objectOptions($choices['tlds'], 'extension', 'extension'), $filters);
            $registrars = $choices['registrars']
                ? array_combine($choices['registrars'], $choices['registrars'])
                : [];
            $fields .= $this->selectField('registrar', 'Registrador', $registrars, $filters);
            $fields .= $this->selectField('period', 'Período', [1 => '1 ano', 2 => '2 anos', 3 => '3 anos', 4 => '4 anos', 5 => '5 anos', 6 => '6 anos', 7 => '7 anos', 8 => '8 anos', 9 => '9 anos', 10 => '10 anos'], $filters);
            $fields .= $this->selectField('status', 'Status', $this->domainStatuses(), $filters);
        } else {
            $fields .= $this->selectField('addon_id', 'Addon', $this->objectOptions($choices['addons'], 'id', 'name'), $filters);
            $fields .= $this->selectField('cycle', 'Ciclo', $this->cycles(), $filters);
            $fields .= $this->selectField('status', 'Status', $this->serviceStatuses(), $filters);
        }
        $fields .= $this->selectField('client_id', 'Cliente', $this->clientOptions($choices['clients']), $filters);
        $fields .= $this->selectField('currency_id', 'Moeda', $this->objectOptions($choices['currencies'], 'id', 'code'), $filters);
        $fields .= '<div><label>Preço mínimo</label><input class="form-control" type="text" name="min_price" value="' . Support::e($filters['min_price'] ?? '') . '"></div>'
            . '<div><label>Preço máximo</label><input class="form-control" type="text" name="max_price" value="' . Support::e($filters['max_price'] ?? '') . '"></div>';
        if ($type === 'domain') {
            $fields .= '<div><label>Vencimento inicial</label><input class="form-control" type="date" name="due_from" value="' . Support::e($filters['due_from'] ?? '') . '"></div>'
                . '<div><label>Vencimento final</label><input class="form-control" type="date" name="due_to" value="' . Support::e($filters['due_to'] ?? '') . '"></div>';
        }

        return '<form method="get" action="addonmodules.php" class="av-bp-panel av-bp-filters">'
            . '<input type="hidden" name="module" value="alphavision_bulk_pricing_manager"><input type="hidden" name="page" value="' . Support::e($type) . '">'
            . '<div class="av-bp-panel-title"><h3>Filtros</h3><p>Reduza a população antes de selecionar os registros.</p></div>'
            . '<div class="av-bp-filter-grid">' . $fields . '</div>'
            . '<div class="av-bp-filter-actions"><a class="btn btn-default" href="' . Support::e($this->moduleLink . '&page=' . $type) . '">Limpar</a><button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Aplicar filtros</button></div></form>';
    }

    private function entityTable($type, array $rows)
    {
        $body = '';
        foreach ($rows as $row) {
            $catalog = $row['catalog_value'] === null
                ? '<span class="label label-default" title="' . Support::e($row['catalog_note'] ?? '') . '">Indisponível</span>'
                : Support::e(Support::money($row['catalog_value'], $row['prefix'], $row['suffix']));
            $difference = $row['catalog_value'] === null ? null : $row['catalog_value'] - $row['current_value'];
            $differenceHtml = $difference === null ? 'N/D' : Support::e(Support::money($difference, $row['prefix'], $row['suffix']));
            $body .= '<tr><td><input class="av-bp-item-checkbox" type="checkbox" name="selected[]" value="' . (int) $row['entity_id'] . '"></td>'
                . '<td>' . $this->recordIdentity($row) . '</td>'
                . '<td>' . Support::e($row['client_name']) . '<small>Cliente #' . (int) $row['client_id'] . '</small></td>'
                . '<td>' . Support::e($row['cycle']) . '</td>'
                . '<td>' . $this->genericBadge($row['status']) . '</td>'
                . '<td class="text-right">' . Support::e(Support::money($row['current_value'], $row['prefix'], $row['suffix'])) . '</td>'
                . '<td class="text-right">' . $catalog . '</td>'
                . '<td class="text-right">' . $differenceHtml . '</td></tr>';
        }
        if ($body === '') {
            $body = '<tr><td colspan="8" class="av-bp-empty">Nenhum registro corresponde aos filtros.</td></tr>';
        }

        return '<div class="table-responsive"><table class="table av-bp-table"><thead><tr><th></th><th>Registro</th><th>Cliente</th><th>Ciclo</th><th>Status</th><th class="text-right">Contratado</th><th class="text-right">Catálogo</th><th class="text-right">Diferença</th></tr></thead><tbody>' . $body . '</tbody></table></div>';
    }

    private function confirmationModal(array $simulation, $csrf)
    {
        if ($simulation['valid_count'] < 1) {
            return '';
        }
        return '<div class="modal fade" id="av-bp-confirm-modal" tabindex="-1" role="dialog"><div class="modal-dialog" role="document"><div class="modal-content">'
            . '<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Confirmar alteração em massa</h4></div>'
            . '<div class="modal-body"><p>Serão processados <strong>' . (int) $simulation['valid_count'] . ' registro(s)</strong>. Faturas já emitidas permanecerão inalteradas.</p><div class="alert alert-warning">Esta ação modifica os preços recorrentes contratados. A reversão será possível somente se os registros não sofrerem alterações posteriores.</div></div>'
            . '<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Voltar à revisão</button>'
            . '<form method="post" action="' . Support::e($this->moduleLink) . '" class="av-bp-inline-form"><input type="hidden" name="csrf_token" value="' . Support::e($csrf) . '"><input type="hidden" name="action" value="execute"><input type="hidden" name="simulation_id" value="' . Support::e($simulation['id']) . '"><button type="submit" class="btn btn-danger av-bp-submit-once">Executar alteração</button></form>'
            . '</div></div></div></div>';
    }

    private function reversalButton($batch, $hasReversal, $csrf)
    {
        if ($batch->operation_type === 'reversal') {
            return '<span class="label label-default">Lote de reversão</span>';
        }
        if ($hasReversal) {
            return '<span class="label label-info">Reversão já registrada</span>';
        }
        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return '';
        }
        return '<form method="post" action="' . Support::e($this->moduleLink . '&page=batch&id=' . $batch->id) . '" onsubmit="return confirm(\'Confirma a reversão protegida deste lote?\');">'
            . '<input type="hidden" name="csrf_token" value="' . Support::e($csrf) . '"><input type="hidden" name="action" value="reverse"><input type="hidden" name="batch_id" value="' . (int) $batch->id . '">'
            . '<button class="btn btn-warning av-bp-submit-once" type="submit"><i class="fas fa-undo"></i> Reverter lote</button></form>';
    }

    private function pagination($type, array $filters, $total, $page, $pageSize)
    {
        $pages = max(1, (int) ceil($total / $pageSize));
        if ($pages <= 1) {
            return '';
        }
        $html = '<nav class="av-bp-pagination">';
        $start = max(1, $page - 3);
        $end = min($pages, $page + 3);
        for ($i = $start; $i <= $end; $i++) {
            $query = array_merge(['module' => 'alphavision_bulk_pricing_manager', 'page' => $type, 'p' => $i], $filters);
            $html .= '<a class="' . ($i === $page ? 'active' : '') . '" href="addonmodules.php?' . Support::e(http_build_query($query)) . '">' . $i . '</a>';
        }
        return $html . '</nav>';
    }

    private function selectField($name, $label, array $options, array $filters)
    {
        return '<div><label>' . Support::e($label) . '</label><select class="form-control" name="' . Support::e($name) . '"><option value="">Todos</option>'
            . $this->options($options, $filters[$name] ?? '') . '</select></div>';
    }

    private function options(array $options, $selected)
    {
        $html = '';
        foreach ($options as $value => $label) {
            $html .= '<option value="' . Support::e($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . Support::e($label) . '</option>';
        }
        return $html;
    }

    private function objectOptions($objects, $value, $label)
    {
        $options = [];
        foreach ($objects as $object) {
            $options[(string) $object->{$value}] = (string) $object->{$label};
        }
        return $options;
    }

    private function clientOptions($clients)
    {
        $options = [];
        foreach ($clients as $client) {
            $person = trim($client->firstname . ' ' . $client->lastname);
            $options[$client->id] = trim((string) $client->companyname) !== ''
                ? $client->companyname . ' (' . $person . ')'
                : $person;
        }
        return $options;
    }

    private function card($label, $value, $icon)
    {
        return '<div class="av-bp-card"><i class="fas ' . Support::e($icon) . '"></i><div><span>' . Support::e($label) . '</span><strong>' . Support::e($value) . '</strong></div></div>';
    }

    private function notice($notice)
    {
        if (!$notice) {
            return '';
        }
        return '<div class="alert alert-' . Support::e($notice['type']) . '">' . Support::e($notice['message']) . '</div>';
    }

    private function entityMeta($type)
    {
        $map = [
            'service' => ['singular' => 'Serviço', 'plural' => 'Produtos e Serviços', 'description' => 'Selecione contratos e atualize somente os preços desejados.'],
            'domain' => ['singular' => 'Domínio', 'plural' => 'Domínios', 'description' => 'Atualize renovações respeitando TLD, moeda e período contratado.'],
            'addon' => ['singular' => 'Addon', 'plural' => 'Addons', 'description' => 'Gerencie preços recorrentes dos addons contratados.'],
        ];
        return $map[$type] ?? ['singular' => ucfirst((string) $type), 'plural' => ucfirst((string) $type), 'description' => ''];
    }

    private function cycles()
    {
        return ['Monthly' => 'Mensal', 'Quarterly' => 'Trimestral', 'Semi-Annually' => 'Semestral', 'Annually' => 'Anual', 'Biennially' => 'Bienal', 'Triennially' => 'Trienal'];
    }

    private function serviceStatuses()
    {
        return ['Active' => 'Ativo', 'Suspended' => 'Suspenso', 'Pending' => 'Pendente', 'Cancelled' => 'Cancelado', 'Terminated' => 'Encerrado', 'Fraud' => 'Fraude'];
    }

    private function domainStatuses()
    {
        return ['Active' => 'Ativo', 'Pending' => 'Pendente', 'Pending Transfer' => 'Transferência pendente', 'Expired' => 'Expirado', 'Cancelled' => 'Cancelado', 'Fraud' => 'Fraude', 'Transferred Away' => 'Transferido', 'Grace' => 'Carência', 'Redemption' => 'Redenção'];
    }

    private function operationLabel($operation)
    {
        if ($operation === 'reversal') {
            return 'Reversão protegida';
        }
        return PriceCalculator::operations()[$operation] ?? $operation;
    }

    private function recordIdentity(array $row)
    {
        $type = (string) ($row['entity_type'] ?? '');
        $id = (int) ($row['entity_id'] ?? 0);
        $itemName = trim((string) ($row['item_name'] ?? ''));
        $identifier = trim((string) ($row['identifier'] ?? ''));

        if ($type === 'domain') {
            $primary = $identifier !== '' ? $identifier : ($itemName !== '' ? $itemName : 'Domínio');
            $details = [];
            if ($itemName !== '' && $itemName !== $primary) {
                $details[] = 'TLD ' . $itemName;
            }
            if (!empty($row['registrar'])) {
                $details[] = 'Registrador ' . $row['registrar'];
            }
            $details[] = 'Domínio #' . $id;
        } else {
            $primary = $itemName !== '' ? $itemName : 'Registro';
            $details = [];
            if ($identifier !== '') {
                $details[] = $identifier;
            }
            $details[] = ($type === 'addon' ? 'Addon #' : 'Serviço #') . $id;
        }

        return '<strong>' . Support::e($primary) . '</strong><small>' . Support::e(implode(' | ', $details)) . '</small>';
    }

    private function operationValue($batch)
    {
        $value = number_format((float) $batch->operation_value, 2, ',', '.');
        if (in_array($batch->operation_type, [PriceCalculator::INCREASE_PERCENT, PriceCalculator::DECREASE_PERCENT], true)) {
            return $value . '%';
        }
        return trim($value . ' ' . (string) $batch->currency);
    }

    private function batchMoney($value, $currency)
    {
        return trim(number_format((float) $value, 2, ',', '.') . ' ' . (string) $currency);
    }

    private function signedMoney($value, $currency)
    {
        $sign = (float) $value > 0 ? '+' : '';
        return trim($sign . number_format((float) $value, 2, ',', '.') . ' ' . (string) $currency);
    }

    private function decodedFilters($json)
    {
        $filters = json_decode((string) $json, true);
        return is_array($filters) ? array_filter($filters, static function ($value): bool {
            return $value !== '' && $value !== null && $value !== [];
        }) : [];
    }

    private function filterSummary($json)
    {
        $filters = $this->decodedFilters($json);
        if (!$filters) {
            return ['title' => 'Nenhum filtro adicional', 'detail' => 'Os registros foram escolhidos manualmente.'];
        }

        $labels = [
            'group_id' => 'Grupo', 'product_id' => 'Produto', 'addon_id' => 'Addon',
            'client_id' => 'Cliente', 'currency_id' => 'Moeda', 'cycle' => 'Ciclo',
            'status' => 'Status', 'query' => 'Pesquisa', 'min_price' => 'Preço mínimo',
            'max_price' => 'Preço máximo', 'tld' => 'TLD', 'registrar' => 'Registrador',
            'period' => 'Período', 'due_from' => 'Vencimento inicial', 'due_to' => 'Vencimento final',
            'original_batch_id' => 'Lote original',
        ];
        $parts = [];
        foreach ($filters as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = ($labels[$key] ?? $key) . ': ' . $value;
            }
        }

        return [
            'title' => count($parts) . ' filtro(s) registrado(s)',
            'detail' => $parts ? implode(' | ', $parts) : 'Filtros técnicos registrados no lote.',
        ];
    }

    private function auditSummaryItem($label, $value, $detail)
    {
        return '<div><span>' . Support::e($label) . '</span><strong>' . Support::e($value) . '</strong>'
            . ($detail !== '' ? '<small>' . Support::e($detail) . '</small>' : '') . '</div>';
    }

    private function auditItemContext($item, $live)
    {
        $stored = json_decode((string) $item->context_json, true);
        if (!is_array($stored)) {
            $stored = [];
        }

        $label = trim((string) $item->entity_label);
        if ($label === '' && is_array($live)) {
            $label = $item->entity_type === 'domain'
                ? (string) $live['identifier']
                : (string) $live['item_name'];
        }
        if ($label === '') {
            $label = 'Registro não disponível';
        }

        return [
            'label' => $label,
            'client_name' => trim((string) $item->client_name) !== ''
                ? (string) $item->client_name
                : (is_array($live) ? (string) $live['client_name'] : ''),
            'identifier' => (string) ($stored['identifier'] ?? (is_array($live) ? $live['identifier'] : '')),
            'cycle' => (string) ($stored['cycle'] ?? (is_array($live) ? $live['cycle'] : '')),
            'status' => (string) ($stored['status'] ?? (is_array($live) ? $live['status'] : '')),
            'registrar' => (string) ($stored['registrar'] ?? (is_array($live) ? ($live['registrar'] ?? '') : '')),
        ];
    }

    private function contextSuffix(array $context)
    {
        $parts = [];
        if ($context['identifier'] !== '' && $context['identifier'] !== $context['label']) {
            $parts[] = $context['identifier'];
        }
        if ($context['cycle'] !== '') {
            $parts[] = $context['cycle'];
        }
        if ($context['registrar'] !== '') {
            $parts[] = 'Registrador ' . $context['registrar'];
        }
        if ($context['status'] !== '') {
            $parts[] = 'Status ' . $context['status'];
        }

        return $parts ? ' | ' . implode(' | ', $parts) : '';
    }

    private function statusBadge($status)
    {
        $class = $status === 'completed' ? 'success' : ($status === 'partial' ? 'warning' : ($status === 'failed' ? 'danger' : 'default'));
        return '<span class="label label-' . $class . '">' . Support::e($this->statusText($status)) . '</span>';
    }

    private function genericBadge($status)
    {
        $class = in_array($status, ['Active'], true) ? 'success' : (in_array($status, ['Suspended', 'Pending', 'Grace'], true) ? 'warning' : 'default');
        return '<span class="label label-' . $class . '">' . Support::e($status) . '</span>';
    }

    private function statusText($status)
    {
        $map = ['processing' => 'Processando', 'completed' => 'Concluído', 'partial' => 'Parcial', 'failed' => 'Falhou'];
        return $map[$status] ?? $status;
    }

    private function dateTime($value)
    {
        $timestamp = strtotime((string) $value);
        return $timestamp ? date('d/m/Y H:i:s', $timestamp) : (string) $value;
    }
}
