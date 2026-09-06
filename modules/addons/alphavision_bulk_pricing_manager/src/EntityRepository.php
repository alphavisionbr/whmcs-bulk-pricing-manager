<?php

namespace Alphavision\BulkPricing;

use WHMCS\Database\Capsule;

final class EntityRepository
{
    private $domainExtensions;

    public function listing($entityType, array $filters, $limit, $offset)
    {
        switch ($entityType) {
            case 'service':
                return $this->services($filters, $limit, $offset);
            case 'domain':
                return $this->domains($filters, $limit, $offset);
            case 'addon':
                return $this->addons($filters, $limit, $offset);
            default:
                throw new \InvalidArgumentException('Tipo de entidade inválido.');
        }
    }

    public function findByIds($entityType, array $ids)
    {
        $filters = ['ids' => $ids];
        $result = $this->listing($entityType, $filters, max(count($ids), 1), 0);
        return $result['rows'];
    }

    public function updateAmount($entityType, $entityId, $newValue)
    {
        $map = [
            'service' => ['tblhosting', 'amount'],
            'domain' => ['tbldomains', 'recurringamount'],
            'addon' => ['tblhostingaddons', 'recurring'],
        ];

        if (!isset($map[$entityType])) {
            throw new \InvalidArgumentException('Tipo de entidade inválido.');
        }

        list($table, $column) = $map[$entityType];
        $affected = Capsule::table($table)
            ->where('id', (int) $entityId)
            ->update([$column => number_format((float) $newValue, 2, '.', '')]);

        if ($affected !== 1) {
            $current = Capsule::table($table)->where('id', (int) $entityId)->value($column);
            if ($current === null || abs((float) $current - (float) $newValue) > 0.0001) {
                throw new \RuntimeException('O registro não foi atualizado.');
            }
        }
    }

    public function choices($entityType)
    {
        $base = [
            'currencies' => Capsule::table('tblcurrencies')
                ->orderBy('default', 'desc')->orderBy('code')->get(['id', 'code']),
            'clients' => Capsule::table('tblclients')
                ->where('status', '!=', 'Closed')
                ->orderBy('firstname')->orderBy('lastname')
                ->get(['id', 'firstname', 'lastname', 'companyname']),
        ];

        if ($entityType === 'service') {
            $base['groups'] = Capsule::table('tblproductgroups')->orderBy('order')->get(['id', 'name']);
            $base['products'] = Capsule::table('tblproducts')->orderBy('gid')->orderBy('order')->get(['id', 'gid', 'name']);
        } elseif ($entityType === 'domain') {
            $base['tlds'] = Capsule::table('tbldomainpricing')->orderBy('order')->get(['id', 'extension']);
            $base['registrars'] = Capsule::table('tbldomains')->where('registrar', '!=', '')
                ->distinct()->orderBy('registrar')->pluck('registrar')->all();
        } elseif ($entityType === 'addon') {
            $base['addons'] = Capsule::table('tbladdons')->orderBy('name')->get(['id', 'name']);
        }

        return $base;
    }

    public function dashboardMetrics()
    {
        $last = Capsule::table('mod_av_bulkpricing_batches')
            ->orderBy('id', 'desc')->first();
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));

        return [
            'last_batch' => $last,
            'batches_30_days' => Capsule::table('mod_av_bulkpricing_batches')
                ->where('created_at', '>=', $since)->count(),
            'items_30_days' => Capsule::table('mod_av_bulkpricing_items')
                ->where('created_at', '>=', $since)->where('result', 'success')->count(),
            'service_count' => Capsule::table('tblhosting')->whereIn('domainstatus', ['Active', 'Suspended'])->count(),
            'domain_count' => Capsule::table('tbldomains')->whereIn('status', ['Active', 'Grace', 'Redemption'])->count(),
            'addon_count' => Capsule::table('tblhostingaddons')->whereIn('status', ['Active', 'Suspended'])->count(),
        ];
    }

    private function services(array $filters, $limit, $offset)
    {
        $query = Capsule::table('tblhosting as h')
            ->join('tblclients as c', 'c.id', '=', 'h.userid')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->leftJoin('tblproductgroups as pg', 'pg.id', '=', 'p.gid')
            ->leftJoin('tblcurrencies as cur', 'cur.id', '=', 'c.currency')
            ->leftJoin('tblpricing as pr', function ($join) {
                $join->on('pr.relid', '=', 'p.id')
                    ->on('pr.currency', '=', 'c.currency')
                    ->where('pr.type', '=', 'product');
            });

        if (!empty($filters['ids'])) {
            $query->whereIn('h.id', array_map('intval', $filters['ids']));
        }
        $this->whereInt($query, 'p.gid', $filters, 'group_id');
        $this->whereInt($query, 'h.packageid', $filters, 'product_id');
        $this->whereInt($query, 'h.userid', $filters, 'client_id');
        $this->whereInt($query, 'c.currency', $filters, 'currency_id');
        $this->whereExact($query, 'h.billingcycle', $filters, 'cycle');
        $this->whereExact($query, 'h.domainstatus', $filters, 'status');
        $this->whereRange($query, 'h.amount', $filters);

        if (!empty($filters['query'])) {
            $term = '%' . trim($filters['query']) . '%';
            $query->where(function ($nested) use ($term) {
                $nested->where('h.domain', 'like', $term)
                    ->orWhere('p.name', 'like', $term)
                    ->orWhere('c.firstname', 'like', $term)
                    ->orWhere('c.lastname', 'like', $term)
                    ->orWhere('c.companyname', 'like', $term)
                    ->orWhere('h.id', '=', (int) trim($term, '%'));
            });
        }

        $total = (clone $query)->count('h.id');
        $rows = $query->orderBy('h.id', 'desc')->offset($offset)->limit($limit)->get([
            'h.id as entity_id', 'h.userid as client_id', 'h.domain as identifier',
            'h.billingcycle as cycle', 'h.amount as current_value', 'h.domainstatus as status',
            'h.promoid', 'h.qty',
            'p.name as item_name', 'pg.name as group_name',
            'c.firstname', 'c.lastname', 'c.companyname',
            'cur.code as currency', 'cur.prefix', 'cur.suffix',
            'pr.monthly', 'pr.quarterly', 'pr.semiannually', 'pr.annually',
            'pr.biennially', 'pr.triennially',
        ]);

        $this->attachConfigOptionCatalogTotals($rows);
        return ['total' => $total, 'rows' => $this->normalizeRecurringRows($rows, 'service')];
    }

    private function addons(array $filters, $limit, $offset)
    {
        $query = Capsule::table('tblhostingaddons as ha')
            ->join('tblhosting as h', 'h.id', '=', 'ha.hostingid')
            ->join('tblclients as c', 'c.id', '=', 'h.userid')
            ->join('tbladdons as a', 'a.id', '=', 'ha.addonid')
            ->leftJoin('tblcurrencies as cur', 'cur.id', '=', 'c.currency')
            ->leftJoin('tblpricing as pr', function ($join) {
                $join->on('pr.relid', '=', 'a.id')
                    ->on('pr.currency', '=', 'c.currency')
                    ->where('pr.type', '=', 'addon');
            });

        if (!empty($filters['ids'])) {
            $query->whereIn('ha.id', array_map('intval', $filters['ids']));
        }
        $this->whereInt($query, 'ha.addonid', $filters, 'addon_id');
        $this->whereInt($query, 'h.userid', $filters, 'client_id');
        $this->whereInt($query, 'c.currency', $filters, 'currency_id');
        $this->whereExact($query, 'ha.billingcycle', $filters, 'cycle');
        $this->whereExact($query, 'ha.status', $filters, 'status');
        $this->whereRange($query, 'ha.recurring', $filters);

        if (!empty($filters['query'])) {
            $term = '%' . trim($filters['query']) . '%';
            $query->where(function ($nested) use ($term) {
                $nested->where('a.name', 'like', $term)
                    ->orWhere('h.domain', 'like', $term)
                    ->orWhere('c.firstname', 'like', $term)
                    ->orWhere('c.lastname', 'like', $term)
                    ->orWhere('c.companyname', 'like', $term);
            });
        }

        $total = (clone $query)->count('ha.id');
        $rows = $query->orderBy('ha.id', 'desc')->offset($offset)->limit($limit)->get([
            'ha.id as entity_id', 'h.userid as client_id', 'h.domain as identifier',
            'ha.billingcycle as cycle', 'ha.recurring as current_value', 'ha.status',
            'a.name as item_name', 'c.firstname', 'c.lastname', 'c.companyname',
            'cur.code as currency', 'cur.prefix', 'cur.suffix',
            'pr.monthly', 'pr.quarterly', 'pr.semiannually', 'pr.annually',
            'pr.biennially', 'pr.triennially',
        ]);

        return ['total' => $total, 'rows' => $this->normalizeRecurringRows($rows, 'addon')];
    }

    private function domains(array $filters, $limit, $offset)
    {
        $query = Capsule::table('tbldomains as d')
            ->join('tblclients as c', 'c.id', '=', 'd.userid')
            ->leftJoin('tblcurrencies as cur', 'cur.id', '=', 'c.currency');

        if (!empty($filters['ids'])) {
            $query->whereIn('d.id', array_map('intval', $filters['ids']));
        }
        $this->whereInt($query, 'd.userid', $filters, 'client_id');
        $this->whereInt($query, 'c.currency', $filters, 'currency_id');
        $this->whereExact($query, 'd.registrar', $filters, 'registrar');
        $this->whereExact($query, 'd.status', $filters, 'status');
        $this->whereInt($query, 'd.registrationperiod', $filters, 'period');
        $this->whereRange($query, 'd.recurringamount', $filters);

        if (!empty($filters['tld'])) {
            $tld = strtolower(trim((string) $filters['tld']));
            $tld = strpos($tld, '.') === 0 ? $tld : '.' . $tld;
            $query->where('d.domain', 'like', '%' . $tld);
        }
        if (!empty($filters['due_from'])) {
            $query->where('d.nextduedate', '>=', $filters['due_from']);
        }
        if (!empty($filters['due_to'])) {
            $query->where('d.nextduedate', '<=', $filters['due_to']);
        }
        if (!empty($filters['query'])) {
            $term = '%' . trim($filters['query']) . '%';
            $query->where(function ($nested) use ($term) {
                $nested->where('d.domain', 'like', $term)
                    ->orWhere('c.firstname', 'like', $term)
                    ->orWhere('c.lastname', 'like', $term)
                    ->orWhere('c.companyname', 'like', $term);
            });
        }

        $total = (clone $query)->count('d.id');
        $rows = $query->orderBy('d.id', 'desc')->offset($offset)->limit($limit)->get([
            'd.id as entity_id', 'd.userid as client_id', 'd.domain as identifier',
            'd.registrationperiod as cycle', 'd.recurringamount as current_value',
            'd.status', 'd.registrar', 'd.expirydate', 'd.nextduedate',
            'd.promoid', 'd.dnsmanagement', 'd.emailforwarding', 'd.idprotection', 'd.is_premium',
            'c.currency as currency_id', 'c.firstname', 'c.lastname', 'c.companyname',
            'cur.code as currency', 'cur.prefix', 'cur.suffix',
        ]);

        return ['total' => $total, 'rows' => $this->normalizeDomains($rows)];
    }

    private function normalizeRecurringRows($rows, $entityType)
    {
        $normalized = [];
        foreach ($rows as $row) {
            $cycleField = $this->cycleField($row->cycle);
            $catalog = $cycleField && isset($row->{$cycleField}) && (float) $row->{$cycleField} >= 0
                ? round((float) $row->{$cycleField}, 2)
                : null;
            $catalogNote = '';

            if ($entityType === 'service' && $catalog !== null) {
                $catalog *= max(1, (int) ($row->qty ?? 1));
                if (isset($row->config_catalog_valid) && !$row->config_catalog_valid) {
                    $catalog = null;
                    $catalogNote = 'Uma opção configurável não possui preço vigente para este ciclo e moeda.';
                } else {
                    $catalog += (float) ($row->config_catalog_total ?? 0);
                    $catalog = round($catalog, 2);
                }
            }
            if ($entityType === 'service' && (int) ($row->promoid ?? 0) > 0) {
                $catalog = null;
                $catalogNote = 'Serviço com promoção ativa. Use uma operação manual para preservar a decisão comercial.';
            }

            $normalized[] = [
                'entity_type' => $entityType,
                'entity_id' => (int) $row->entity_id,
                'client_id' => (int) $row->client_id,
                'client_name' => $this->clientName($row),
                'item_name' => (string) $row->item_name,
                'identifier' => (string) ($row->identifier ?: ''),
                'cycle' => (string) $row->cycle,
                'current_value' => round((float) $row->current_value, 2),
                'catalog_value' => $catalog,
                'catalog_note' => $catalogNote,
                'currency' => (string) ($row->currency ?: ''),
                'prefix' => (string) ($row->prefix ?: ''),
                'suffix' => (string) ($row->suffix ?: ''),
                'status' => (string) $row->status,
            ];
        }
        return $normalized;
    }

    private function normalizeDomains($rows)
    {
        $extensions = $this->domainExtensions();
        $pricingRows = Capsule::table('tblpricing')
            ->where('type', 'domainrenew')->get();
        $pricing = [];
        foreach ($pricingRows as $price) {
            $pricing[(int) $price->currency][(int) $price->relid] = $price;
        }

        $normalized = [];
        foreach ($rows as $row) {
            $domain = strtolower((string) $row->identifier);
            $domainPricingId = null;
            $matchedTld = '';
            foreach ($extensions as $extension) {
                if ($this->endsWith($domain, $extension['normalized'])) {
                    $domainPricingId = $extension['id'];
                    $matchedTld = $extension['display'];
                    break;
                }
            }

            $catalog = null;
            $field = $this->domainPeriodField((int) $row->cycle);
            if ($domainPricingId && $field && isset($pricing[(int) $row->currency_id][$domainPricingId])) {
                $price = $pricing[(int) $row->currency_id][$domainPricingId];
                if (isset($price->{$field}) && (float) $price->{$field} >= 0) {
                    $catalog = round((float) $price->{$field}, 2);
                }
            }

            $catalogNote = '';
            if ((int) $row->promoid > 0) {
                $catalog = null;
                $catalogNote = 'Domínio com promoção ativa.';
            } elseif ((int) $row->dnsmanagement || (int) $row->emailforwarding || (int) $row->idprotection) {
                $catalog = null;
                $catalogNote = 'Domínio com addon ativo. A sincronização simples poderia remover sua cobrança.';
            } elseif ((int) $row->is_premium) {
                $catalog = null;
                $catalogNote = 'Domínio premium. O preço específico do registro deve ser preservado.';
            }

            $normalized[] = [
                'entity_type' => 'domain',
                'entity_id' => (int) $row->entity_id,
                'client_id' => (int) $row->client_id,
                'client_name' => $this->clientName($row),
                'item_name' => $matchedTld !== '' ? $matchedTld : 'TLD não localizada',
                'identifier' => (string) $row->identifier,
                'cycle' => (int) $row->cycle . ' ano(s)',
                'period' => (int) $row->cycle,
                'current_value' => round((float) $row->current_value, 2),
                'catalog_value' => $catalog,
                'catalog_note' => $catalogNote,
                'currency' => (string) ($row->currency ?: ''),
                'prefix' => (string) ($row->prefix ?: ''),
                'suffix' => (string) ($row->suffix ?: ''),
                'status' => (string) $row->status,
                'registrar' => (string) $row->registrar,
                'expirydate' => (string) $row->expirydate,
                'nextduedate' => (string) $row->nextduedate,
            ];
        }
        return $normalized;
    }

    private function domainExtensions()
    {
        if ($this->domainExtensions !== null) {
            return $this->domainExtensions;
        }

        $items = [];
        foreach (Capsule::table('tbldomainpricing')->get(['id', 'extension']) as $row) {
            $display = strtolower(trim((string) $row->extension));
            $normalized = strpos($display, '.') === 0 ? $display : '.' . $display;
            $items[] = ['id' => (int) $row->id, 'display' => $display, 'normalized' => $normalized];
        }
        usort($items, function ($a, $b) {
            return strlen($b['normalized']) <=> strlen($a['normalized']);
        });

        $this->domainExtensions = $items;
        return $items;
    }

    private function attachConfigOptionCatalogTotals($serviceRows)
    {
        $serviceIds = [];
        $serviceMap = [];
        foreach ($serviceRows as $row) {
            $row->config_catalog_total = 0.0;
            $row->config_catalog_valid = true;
            $serviceIds[] = (int) $row->entity_id;
            $serviceMap[(int) $row->entity_id] = $row;
        }

        if (!$serviceIds) {
            return;
        }

        $options = Capsule::table('tblhostingconfigoptions as hco')
            ->join('tblhosting as h', 'h.id', '=', 'hco.relid')
            ->join('tblclients as c', 'c.id', '=', 'h.userid')
            ->leftJoin('tblproductconfigoptions as pco', 'pco.id', '=', 'hco.configid')
            ->leftJoin('tblpricing as pr', function ($join) {
                $join->on('pr.relid', '=', 'hco.optionid')
                    ->on('pr.currency', '=', 'c.currency')
                    ->where('pr.type', '=', 'configoptions');
            })
            ->whereIn('hco.relid', $serviceIds)
            ->get([
                'hco.relid as service_id', 'hco.qty', 'pco.optiontype',
                'h.billingcycle as cycle', 'pr.id as pricing_id',
                'pr.monthly', 'pr.quarterly', 'pr.semiannually',
                'pr.annually', 'pr.biennially', 'pr.triennially',
            ]);

        foreach ($options as $option) {
            $service = $serviceMap[(int) $option->service_id] ?? null;
            if (!$service) {
                continue;
            }
            $field = $this->cycleField($option->cycle);
            if (!$field || !$option->pricing_id || !isset($option->{$field}) || (float) $option->{$field} < 0) {
                $service->config_catalog_valid = false;
                continue;
            }
            $multiplier = (int) $option->optiontype === 4 ? max(0, (int) $option->qty) : 1;
            $service->config_catalog_total += (float) $option->{$field} * $multiplier;
        }
    }

    private function cycleField($cycle)
    {
        $key = strtolower(str_replace(['-', ' '], '', (string) $cycle));
        $map = [
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'semiannually' => 'semiannually',
            'annually' => 'annually',
            'biennially' => 'biennially',
            'triennially' => 'triennially',
        ];
        return $map[$key] ?? null;
    }

    private function domainPeriodField($period)
    {
        $map = [
            1 => 'msetupfee', 2 => 'qsetupfee', 3 => 'ssetupfee', 4 => 'asetupfee',
            5 => 'bsetupfee', 6 => 'monthly', 7 => 'quarterly', 8 => 'semiannually',
            9 => 'annually', 10 => 'biennially',
        ];
        return $map[$period] ?? null;
    }

    private function clientName($row)
    {
        $person = trim((string) $row->firstname . ' ' . (string) $row->lastname);
        return trim((string) $row->companyname) !== ''
            ? trim((string) $row->companyname) . ' (' . $person . ')'
            : $person;
    }

    private function endsWith($value, $suffix)
    {
        return $suffix === '' || substr($value, -strlen($suffix)) === $suffix;
    }

    private function whereInt($query, $column, array $filters, $key)
    {
        if (isset($filters[$key]) && (int) $filters[$key] > 0) {
            $query->where($column, (int) $filters[$key]);
        }
    }

    private function whereExact($query, $column, array $filters, $key)
    {
        if (isset($filters[$key]) && trim((string) $filters[$key]) !== '') {
            $query->where($column, trim((string) $filters[$key]));
        }
    }

    private function whereRange($query, $column, array $filters)
    {
        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where($column, '>=', (float) Support::decimal($filters['min_price']));
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where($column, '<=', (float) Support::decimal($filters['max_price']));
        }
    }
}
