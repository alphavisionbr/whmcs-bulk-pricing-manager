<?php
/**
 * Hooks do Alphavision WHMCS Bulk Pricing Manager.
 */

declare(strict_types=1);

if (!defined('WHMCS')) {
    exit('Acesso direto não permitido.');
}

require_once __DIR__ . '/bootstrap.php';

/**
 * Mantém o nome oficial completo nas telas de gerenciamento e configuração e
 * reduz exclusivamente o rótulo usado pelo template do menu Addons.
 *
 * O WHMCS expõe a lista de addons acessíveis em `addon_modules`. A alteração
 * ocorre antes da renderização do template administrativo e não modifica o
 * valor `name` retornado pela função config() do módulo.
 */
add_hook('AdminAreaPage', 1, static function (array $vars): array {
    if (!isset($vars['addon_modules']) || !is_array($vars['addon_modules'])) {
        return [];
    }

    $modules = $vars['addon_modules'];
    $moduleKey = 'alphavision_bulk_pricing_manager';
    $officialName = 'Alphavision WHMCS Bulk Pricing Manager';
    $menuName = 'Preços em Massa';

    if (array_key_exists($moduleKey, $modules)) {
        if (is_array($modules[$moduleKey])) {
            foreach (['name', 'displayName', 'displayname', 'label', 'title'] as $field) {
                if (array_key_exists($field, $modules[$moduleKey])) {
                    $modules[$moduleKey][$field] = $menuName;
                }
            }
        } else {
            $modules[$moduleKey] = $menuName;
        }
    }

    foreach ($modules as $key => &$module) {
        if (!is_array($module)) {
            if ((string) $module === $officialName && (string) $key === $moduleKey) {
                $module = $menuName;
            }
            continue;
        }

        $identifier = (string) ($module['module'] ?? $module['filename'] ?? $module['key'] ?? $key);
        $currentName = (string) ($module['name'] ?? $module['displayName'] ?? $module['displayname'] ?? $module['label'] ?? $module['title'] ?? '');

        if ($identifier !== $moduleKey && $currentName !== $officialName) {
            continue;
        }

        foreach (['name', 'displayName', 'displayname', 'label', 'title'] as $field) {
            if (array_key_exists($field, $module)) {
                $module[$field] = $menuName;
            }
        }
    }
    unset($module);

    return ['addon_modules' => $modules];
});

/**
 * Fallback visual para instalações em que o template administrativo monta o
 * menu antes de aplicar a variável devolvida por AdminAreaPage.
 *
 * A rotina altera somente o link exato deste addon e não interfere na gestão
 * dos módulos, na página de configuração ou em outros itens do menu.
 */
add_hook('AdminAreaFooterOutput', 1, static function (array $vars): string {
    unset($vars);

    return <<<'HTML'
<script id="av-bulk-pricing-menu-label">
(function () {
    var moduleKey = 'alphavision_bulk_pricing_manager';
    var officialName = 'Alphavision WHMCS Bulk Pricing Manager';
    var menuName = 'Preços em Massa';

    function updateMenuLabel() {
        document.querySelectorAll('a[href]').forEach(function (link) {
            var url;
            try {
                url = new URL(link.href, window.location.href);
            } catch (error) {
                return;
            }

            if (url.searchParams.get('module') !== moduleKey) {
                return;
            }

            if (link.textContent.replace(/\s+/g, ' ').trim() !== officialName) {
                return;
            }

            link.textContent = menuName;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateMenuLabel);
    } else {
        updateMenuLabel();
    }
}());
</script>
HTML;
});
