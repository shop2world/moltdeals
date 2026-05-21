<?php
class SmartAffiliate {
    public static function isAlreadyAffiliate($url) {
        $markers = ['tag=', 'campid=', 'linksynergy', 'anrdoezrs', 'sjv.io', 'pxf.io', 'tkqlhce', 'jdoqocy'];
        foreach ($markers as $m) if (stripos($url, $m) !== false) return true;
        return false;
    }

    public static function buildUrl($url, $ownerIds, $clickCounter, $defaults = []) {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $isAmazon = (strpos($host, 'amazon.com') !== false || strpos($host, 'amzn.to') !== false);
        $isEbay   = (strpos($host, 'ebay.com') !== false);

        if (!$isAmazon && !$isEbay) {
            return ['url' => $url, 'network' => 'other', 'tag_owner' => 'none', 'action' => 'passthrough'];
        }

        $network = $isAmazon ? 'amazon' : 'ebay';
        
        // 1-in-100 logic
        $usePlatformTag = ($clickCounter > 0 && $clickCounter % 100 === 0);
        
        // Tag Selection
        $tagOwner = 'user_own';
        $tag = trim($ownerIds[$network] ?? '');
        
        if (empty($tag)) {
            $tag = trim($defaults[$network] ?? '');
            $tagOwner = 'platform_default';
        }
        
        if ($usePlatformTag) {
            $tag = trim($defaults[$network] ?? '');
            $tagOwner = 'platform_fee';
        }

        if (empty($tag)) {
            return ['url' => $url, 'network' => $network, 'tag_owner' => 'none_found', 'action' => 'passthrough'];
        }

        // Apply Tag
        if ($isAmazon) {
            $url = preg_replace('/([?&])tag=[^&]*/', '', $url);
            $url = rtrim($url, '?&');
            $sep = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $sep . 'tag=' . urlencode($tag);
        } else if ($isEbay) {
            // New eBay EPN Format (direct parameter append)
            // Removes old rover/epn parameters if they exist to prevent duplication
            $url = preg_replace('/([?&])(mkcid|mkrid|siteid|campid|customid|toolid|mkevt)=[^&]*/', '', $url);
            $url = rtrim($url, '?&');
            $sep = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $sep . "mkcid=1&mkrid=711-53200-19255-0&siteid=0&campid=" . urlencode($tag) . "&customid=moltdeals&toolid=10001&mkevt=1";
        }

        return ['url' => $url, 'network' => $network, 'tag_owner' => $tagOwner, 'action' => 'tagged'];
    }
}