<?php
// config.php

return [
    'cache_time' => 900, // 15 minutes cache lifespan
    'cache_dir'  => __DIR__ . '/cache',
    'feeds'      => [
        'russiatoday' => [
            'name' => 'Russia Today',
            'url'  => 'https://rt-english.com/rss/'
        ]
    ]
];
