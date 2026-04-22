<?php

declare(strict_types=1);

namespace App\Integrations;

use RuntimeException;

class GraphAuthClient
{
    public function authorizationUrl(array $settings, string $state): string
    {
        $tenant = $settings['tenant_id'] ?? 'organizations';
        $params = [
            'client_id' => $settings['client_id'] ?? '',
            'response_type' => 'code',
            'redirect_uri' => $settings['redirect_uri'] ?? '',
            'response_mode' => 'query',
            'scope' => 'offline_access https://graph.microsoft.com/Files.ReadWrite',
            'state' => $state,
        ];

        return 'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/authorize?' . http_build_query($params);
    }

    public function exchangeCode(array $settings, string $code): array
    {
        return $this->tokenRequest($settings, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $settings['redirect_uri'] ?? '',
        ]);
    }

    public function refreshToken(array $settings): array
    {
        if (empty($settings['refresh_token'])) {
            throw new RuntimeException('Refresh token ausente. Autorize a integracao Microsoft novamente.');
        }

        return $this->tokenRequest($settings, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $settings['refresh_token'],
        ]);
    }

    private function tokenRequest(array $settings, array $params): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensao PHP cURL precisa estar habilitada no XAMPP.');
        }

        foreach (['tenant_id', 'client_id', 'client_secret'] as $key) {
            if (empty($settings[$key])) {
                throw new RuntimeException("Configuracao Graph ausente: {$key}.");
            }
        }

        $tenant = $settings['tenant_id'];
        $body = array_merge([
            'client_id' => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
            'scope' => 'offline_access https://graph.microsoft.com/Files.ReadWrite',
        ], $params);

        $ch = curl_init('https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $status >= 400) {
            throw new RuntimeException('Falha ao obter token Microsoft: ' . ($error ?: $raw));
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data) || empty($data['access_token'])) {
            throw new RuntimeException('Resposta de token invalida da Microsoft.');
        }

        return $data;
    }
}
