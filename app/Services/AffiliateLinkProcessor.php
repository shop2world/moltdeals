<?php
namespace App\Services;
use App\Models\User;
class AffiliateLinkProcessor
{
    protected array $platformIds;
    public function __construct()
    {
        $this->platformIds = config('affiliate.platform');
    }
    public function process(string $url, ?User $agentOwner = null): array
    {
        $network = $this->detectNetwork($url);
        $ownerId = $agentOwner?->getAffiliateId($network);
        if ($ownerId) {
            $finalId = $ownerId;
            $revenueOwner = 'user';
        } else {
            $finalId = $this->platformIds[$network] ?? null;
            $revenueOwner = $finalId ? 'platform' : 'none';
        }
        if (! $finalId) {
            return [
                'url' => $url,
                'network' => $network,
                'revenue_owner'=> 'none',
                'monetized' => false,
            ];
        }
        $convertedUrl = $this->applyId($url, $network, $finalId);
        return [
            'url' => $convertedUrl,
            'network' => $network,
            'revenue_owner'=> $revenueOwner,
            'id_used' => $finalId,
            'monetized' => true,
        ];
    }
    public function detectNetwork(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        $host = preg_replace('/^www\./i', '', $host);
        $networks = config('affiliate.networks', []);
        foreach ($networks as $key => $network) {
            foreach ($network['domains'] as $domain) {
                if (str_contains($host, $domain)) {
                    return $key;
                }
            }
        }
        return 'unknown';
    }
    protected function applyId(string $url, string $network, string $id): string
    {
        $networks = config('affiliate.networks');
        $param = $networks[$network]['param'] ?? null;
        if (! $param) {
            return $url;
        }
        return $this->replaceParam($url, $param, $id);
    }
    protected function replaceParam(string $url, string $key, string $value): string
    {
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);
        $params[$key] = $value;
        $scheme = ($parsed['scheme'] ?? 'https') . '://';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        $query = http_build_query($params);
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        return "{$scheme}{$host}{$path}?{$query}{$fragment}";
    }
}
