<?php
return [
    'platform' => [
        'linkshare' => env('PLATFORM_LINKSHARE_ID'),
        'amazon' => env('PLATFORM_AMAZON_TAG'),
        'cj' => env('PLATFORM_CJ_ID'),
        'ebay' => env('PLATFORM_EBAY_ID'),
        'shareasale' => env('PLATFORM_SHAREASALE_ID'),
        'impact' => env('PLATFORM_IMPACT_ID'),
    ],
    'networks' => [
        'linkshare' => [
            'name' => 'Rakuten LinkShare',
            'param' => 'id',
            'example' => 'vtJ18U6LStE',
            'domains' => ['linksynergy.com', 'click.linksynergy.com'],
            'help' => 'https://rakutenadvertising.com',
        ],
        'amazon' => [
            'name' => 'Amazon Associates',
            'param' => 'tag',
            'example' => 'yourname-20',
            'domains' => ['amazon.com', 'amzn.to'],
            'help' => 'https://affiliate-program.amazon.com',
        ],
        'cj' => [
            'name' => 'CJ Affiliate',
            'param' => 'pid',
            'example' => '12345678',
            'domains' => ['anrdoezrs.net', 'tkqlhce.com', 'jdoqocy.com'],
            'help' => 'https://www.cj.com',
        ],
        'ebay' => [
            'name' => 'eBay Partner Network',
            'param' => 'campid',
            'example' => '5338722373',
            'domains' => ['ebay.com', 'rover.ebay.com'],
            'help' => 'https://partnernetwork.ebay.com',
        ],
        'shareasale' => [
            'name' => 'ShareASale',
            'param' => 'u',
            'example' => '123456',
            'domains' => ['shareasale.com'],
            'help' => 'https://www.shareasale.com',
        ],
        'impact' => [
            'name' => 'Impact Radius',
            'param' => 'aff_id',
            'example' => '123456',
            'domains' => ['impact.go2cloud.org'],
            'help' => 'https://impact.com',
        ],
    ],
];
