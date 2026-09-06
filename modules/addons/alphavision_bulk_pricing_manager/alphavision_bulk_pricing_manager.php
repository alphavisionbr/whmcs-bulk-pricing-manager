<?php
/**
 * Alphavision WHMCS Bulk Pricing Manager
 *
 * Atualização seletiva, simulada e auditável de preços recorrentes.
 *
 * @package   AlphavisionWHMCSBulkPricingManager
 * @author    Alphavision®
 * @copyright Copyright (c) 2026 Alphavision®
 * @license   MIT
 * @version   1.0.2
 * @link      https://alphavision.com.br/
 *
 * Compatibilidade declarada:
 * - WHMCS 9.0.5+
 * - PHP 8.3 a 8.5
 *
 * Versão 1.0.2 testada, validada e homologada antes da publicação pública.
 */

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    exit('Acesso direto não permitido.');
}

require_once __DIR__ . '/bootstrap.php';

function alphavision_bulk_pricing_manager_config(): array
{
    $help = static function (string $text): string {
        return '<button type="button" class="av-config-help" title="'
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
            . '" aria-label="Ajuda: ' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '">?</button>';
    };

    $section = static function (string $title, string $description, bool $includeAssets = false): string {
        $assets = '';

        if ($includeAssets) {
            $assets = <<<'HTML'
<style id="av-config-styles">
.av-config-section-marker,.av-config-row-marker{display:none!important}
.av-config-help{display:inline-flex;align-items:center;justify-content:center;width:23px;height:23px;margin-left:8px;padding:0;border:1px solid #8db5d2;border-radius:50%;background:#e8f3fa;color:#075b91;font-size:13px;font-weight:700;line-height:1;cursor:help;vertical-align:middle;box-shadow:none}
.av-config-help:hover,.av-config-help:focus{background:#075b91;color:#fff;border-color:#075b91;outline:0}
tr.av-config-section-row>td,tr.av-config-section-row>th{padding-top:12px!important;padding-bottom:12px!important;background:#e2ebf2!important;border-top:0!important}
tr.av-config-section-row>td:first-child,tr.av-config-section-row>th:first-child{color:#002f57!important;font-size:15px!important;font-weight:700!important;vertical-align:top!important}
tr.av-config-section-row>td:last-child{color:#536675!important;font-size:13px!important;line-height:1.55!important}
tr.av-config-standard-row>td,tr.av-config-standard-row>th{padding-top:9px!important;padding-bottom:9px!important}
tr.av-config-standard-row>td:first-child,tr.av-config-standard-row>th:first-child{font-weight:600!important}
tr.av-config-section-row input[type=text]{display:none!important}
body.av-bulk-pricing-config table.form tr:not(.av-config-section-row)>td,body.av-bulk-pricing-config table.form tr:not(.av-config-section-row)>th{background-color:var(--av-config-cell-bg)!important}
body.av-bulk-pricing-config table.form tr:not(.av-config-section-row):hover>td,body.av-bulk-pricing-config table.form tr:not(.av-config-section-row):hover>th{background-color:var(--av-config-cell-bg)!important}
</style>
<script>
(function(){
  function initAlphavisionConfig(){
    document.body.classList.add('av-bulk-pricing-config');
    var style=document.getElementById('av-config-styles');
    if(style&&style.parentNode!==document.head) document.head.appendChild(style);
    document.querySelectorAll('.av-config-section-marker').forEach(function(marker){
      var row=marker.closest('tr');
      if(!row||row.dataset.avReady==='1') return;
      row.dataset.avReady='1';
      row.classList.add('av-config-section-row');
      var cells=row.querySelectorAll(':scope > th, :scope > td');
      if(cells.length<2) return;
      cells[0].textContent=marker.getAttribute('data-title')||'';
      cells[1].innerHTML='<span class="av-config-section-description">'+(marker.getAttribute('data-description')||'')+'</span>';
    });
    document.querySelectorAll('.av-config-row-marker').forEach(function(marker){
      var row=marker.closest('tr');
      if(!row) return;
      row.classList.add('av-config-standard-row');
      marker.remove();
    });
    document.querySelectorAll('table.form tr:not(.av-config-section-row) > td, table.form tr:not(.av-config-section-row) > th').forEach(function(cell){
      if(cell.style.getPropertyValue('--av-config-cell-bg')) return;
      cell.style.setProperty('--av-config-cell-bg',window.getComputedStyle(cell).backgroundColor);
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',initAlphavisionConfig);
  else initAlphavisionConfig();
})();
</script>
HTML;
        }

        return $assets
            . '<span class="av-config-section-marker" data-title="'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
            . '" data-description="'
            . htmlspecialchars($description, ENT_QUOTES, 'UTF-8')
            . '"></span>';
    };

    $rowMarker = static function (): string {
        return '<span class="av-config-row-marker"></span>';
    };

    return [
        'name' => 'Alphavision WHMCS Bulk Pricing Manager',
        'description' => 'Atualização seletiva e auditável de preços recorrentes de serviços, domínios e addons.',
        'version' => ALPHAVISION_BULK_PRICING_VERSION,
        'author' => 'Alphavision®',
        'language' => 'portuguese-br',
        'fields' => [
            'processing_section' => [
                'FriendlyName' => $section('Processamento', 'Defina quantos registros serão exibidos e processados em cada etapa do módulo.', true),
                'Type' => 'text',
                'Size' => '1',
                'Default' => '',
            ],
            'records_per_page' => [
                'FriendlyName' => 'Registros por página',
                'Type' => 'dropdown',
                'Options' => '25,50,100,200',
                'Default' => '50',
                'Description' => $rowMarker() . 'Quantidade máxima exibida nas telas de seleção.'
                    . $help('A seleção em massa atua apenas sobre os registros carregados na página atual.'),
            ],
            'batch_size' => [
                'FriendlyName' => 'Tamanho do lote interno',
                'Type' => 'dropdown',
                'Options' => '25,50,100,200',
                'Default' => '50',
                'Description' => $rowMarker() . 'Quantidade de registros processada por bloco durante execução e reversão.'
                    . $help('Lotes menores reduzem o consumo de memória. Lotes maiores diminuem a quantidade de consultas.'),
            ],
            'access_control_section' => [
                'FriendlyName' => $section('Controle de acesso', 'O Access Control nativo do WHMCS, exibido abaixo, define quais grupos administrativos podem abrir o addon.'),
                'Type' => 'text',
                'Size' => '1',
                'Default' => '',
            ],
        ],
    ];
}

function alphavision_bulk_pricing_manager_install_schema(): void
{
    $schema = Capsule::schema();

    if (!$schema->hasTable(ALPHAVISION_BULK_PRICING_BATCHES_TABLE)) {
        $schema->create(ALPHAVISION_BULK_PRICING_BATCHES_TABLE, static function ($table): void {
            $table->bigIncrements('id');
            $table->string('operation_uuid', 64)->unique();
            $table->unsignedInteger('admin_id');
            $table->string('entity_type', 24);
            $table->string('operation_type', 32);
            $table->decimal('operation_value', 18, 4)->nullable();
            $table->longText('filters_json')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('total_before', 18, 4)->default(0);
            $table->decimal('total_after', 18, 4)->default(0);
            $table->string('currency', 12)->nullable();
            $table->string('status', 24)->default('processing');
            $table->unsignedBigInteger('reversal_of_batch_id')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('completed_at')->nullable();
            $table->index(['entity_type', 'created_at'], 'av_bp_entity_created');
            $table->index('reversal_of_batch_id', 'av_bp_reversal_of');
        });
    }

    if (!$schema->hasTable(ALPHAVISION_BULK_PRICING_ITEMS_TABLE)) {
        $schema->create(ALPHAVISION_BULK_PRICING_ITEMS_TABLE, static function ($table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id');
            $table->string('entity_type', 24);
            $table->unsignedInteger('entity_id');
            $table->unsignedInteger('client_id')->nullable();
            $table->decimal('value_before', 18, 4);
            $table->decimal('value_after', 18, 4);
            $table->decimal('catalog_value', 18, 4)->nullable();
            $table->string('currency', 12)->nullable();
            $table->string('result', 24);
            $table->text('error_message')->nullable();
            $table->string('entity_label', 255)->nullable();
            $table->string('client_name', 255)->nullable();
            $table->text('context_json')->nullable();
            $table->dateTime('created_at');
            $table->index(['batch_id', 'result'], 'av_bp_batch_result');
            $table->index(['entity_type', 'entity_id'], 'av_bp_entity_item');
        });
    } else {
        $columns = [
            'entity_label' => static function ($table): void {
                $table->string('entity_label', 255)->nullable()->after('error_message');
            },
            'client_name' => static function ($table): void {
                $table->string('client_name', 255)->nullable()->after('entity_label');
            },
            'context_json' => static function ($table): void {
                $table->text('context_json')->nullable()->after('client_name');
            },
        ];

        foreach ($columns as $column => $migration) {
            if (!$schema->hasColumn(ALPHAVISION_BULK_PRICING_ITEMS_TABLE, $column)) {
                $schema->table(ALPHAVISION_BULK_PRICING_ITEMS_TABLE, $migration);
            }
        }
    }
}

function alphavision_bulk_pricing_manager_activate(): array
{
    try {
        alphavision_bulk_pricing_manager_install_schema();

        return [
            'status' => 'success',
            'description' => 'Tabelas de auditoria do Bulk Pricing Manager instaladas.',
        ];
    } catch (Throwable $exception) {
        return [
            'status' => 'error',
            'description' => 'Não foi possível ativar o módulo: ' . $exception->getMessage(),
        ];
    }
}

function alphavision_bulk_pricing_manager_deactivate(): array
{
    return [
        'status' => 'success',
        'description' => 'Módulo desativado. O histórico foi preservado para proteger a auditoria.',
    ];
}

function alphavision_bulk_pricing_manager_upgrade(array $vars): void
{
    unset($vars);
    alphavision_bulk_pricing_manager_install_schema();
}

function alphavision_bulk_pricing_manager_output(array $vars): void
{
    try {
        $controller = new \Alphavision\BulkPricing\AdminController($vars);
        echo $controller->handle();
    } catch (Throwable $exception) {
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'alphavision_bulk_pricing_manager',
                'admin-output',
                ['page' => $_GET['page'] ?? 'dashboard'],
                $exception->getMessage(),
                $exception->getTraceAsString()
            );
        }

        echo '<div class="alert alert-danger"><strong>Falha interna:</strong> '
            . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
            . '</div>';
    }
}
