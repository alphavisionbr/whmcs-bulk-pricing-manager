<?php

namespace Alphavision\BulkPricing;

final class Support
{
    public static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function adminId()
    {
        $adminId = isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;

        if ($adminId < 1) {
            throw new \RuntimeException('A sessão administrativa não foi identificada.');
        }

        return $adminId;
    }

    public static function csrfToken()
    {
        if (empty($_SESSION['av_bulkpricing_csrf'])) {
            $_SESSION['av_bulkpricing_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['av_bulkpricing_csrf'];
    }

    public static function validateCsrf($token)
    {
        $expected = isset($_SESSION['av_bulkpricing_csrf'])
            ? (string) $_SESSION['av_bulkpricing_csrf']
            : '';

        if ($expected === '' || !hash_equals($expected, (string) $token)) {
            throw new \RuntimeException('Token de segurança inválido ou expirado. Atualize a página e tente novamente.');
        }
    }

    public static function requestMethod()
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function money($value, $prefix = '', $suffix = '')
    {
        return trim($prefix . ' ' . number_format((float) $value, 2, ',', '.') . ' ' . $suffix);
    }

    public static function decimal($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (strpos($value, ',') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Informe um valor numérico válido.');
        }

        return round((float) $value, 4);
    }

    public static function ids($input)
    {
        $ids = is_array($input) ? $input : [];
        $ids = array_map('intval', $ids);
        $ids = array_values(array_unique(array_filter($ids, function ($id) {
            return $id > 0;
        })));

        if (!$ids) {
            throw new \InvalidArgumentException('Selecione pelo menos um registro.');
        }

        return $ids;
    }

    public static function uuid()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-'
            . substr($hex, 20);
    }

    public static function now()
    {
        return date('Y-m-d H:i:s');
    }

    public static function sessionPutSimulation(array $simulation)
    {
        self::pruneSimulations();
        $id = bin2hex(random_bytes(18));
        $_SESSION['av_bulkpricing_simulations'][$id] = $simulation;
        return $id;
    }

    public static function sessionPullSimulation($id)
    {
        self::pruneSimulations();
        $id = (string) $id;
        $simulation = $_SESSION['av_bulkpricing_simulations'][$id] ?? null;

        if (!is_array($simulation)) {
            throw new \RuntimeException('A simulação não existe ou expirou. Gere uma nova simulação.');
        }

        unset($_SESSION['av_bulkpricing_simulations'][$id]);
        return $simulation;
    }

    private static function pruneSimulations()
    {
        if (empty($_SESSION['av_bulkpricing_simulations']) || !is_array($_SESSION['av_bulkpricing_simulations'])) {
            $_SESSION['av_bulkpricing_simulations'] = [];
            return;
        }

        $limit = time() - 1800;
        foreach ($_SESSION['av_bulkpricing_simulations'] as $id => $simulation) {
            if ((int) ($simulation['created_timestamp'] ?? 0) < $limit) {
                unset($_SESSION['av_bulkpricing_simulations'][$id]);
            }
        }
    }
}

