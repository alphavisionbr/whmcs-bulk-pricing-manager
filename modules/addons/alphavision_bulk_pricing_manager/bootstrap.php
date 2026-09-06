<?php
/**
 * Bootstrap compartilhado do Alphavision WHMCS Bulk Pricing Manager.
 *
 * Centraliza constantes e o carregamento das classes sem importar novamente
 * o arquivo principal do addon, que contém as funções exigidas pelo WHMCS.
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    exit('Acesso direto não permitido.');
}

if (!defined('ALPHAVISION_BULK_PRICING_VERSION')) {
    define('ALPHAVISION_BULK_PRICING_VERSION', '1.0.2');
}

if (!defined('ALPHAVISION_BULK_PRICING_BATCHES_TABLE')) {
    define('ALPHAVISION_BULK_PRICING_BATCHES_TABLE', 'mod_av_bulkpricing_batches');
}

if (!defined('ALPHAVISION_BULK_PRICING_ITEMS_TABLE')) {
    define('ALPHAVISION_BULK_PRICING_ITEMS_TABLE', 'mod_av_bulkpricing_items');
}

require_once __DIR__ . '/src/Support.php';
require_once __DIR__ . '/src/PriceCalculator.php';
require_once __DIR__ . '/src/EntityRepository.php';
require_once __DIR__ . '/src/AuditRepository.php';
require_once __DIR__ . '/src/PricingService.php';
require_once __DIR__ . '/src/AdminView.php';
require_once __DIR__ . '/src/AdminController.php';
