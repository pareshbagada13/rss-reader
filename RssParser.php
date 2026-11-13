<?php
// RssParser.php

class RssParser
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        if (!is_dir($this->config['cache_dir'])) {
            mkdir($this->config['cache_dir'], 0755, true);
        }
    }

    public function getFeed(string $feedKey): array
    {
        if (!isset($this->config['feeds'][$feedKey])) {
            throw new InvalidArgumentException("Feed key '{$feedKey}' does not exist.");
        }

        $feedUrl   = $this->config['feeds'][$feedKey]['url'];
        $cacheFile = $this->config['cache_dir'] . '/' . md5($feedUrl) . '.json';

        // Check cache validity
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $this->config['cache_time'])) {
            $cachedData = file_get_contents($cacheFile);
            return json_decode($cachedData, true) ?: [];
        }

        // Fetch fresh XML
        $xmlContent = $this->fetchXml($feedUrl);
        if (!$xmlContent) {
            // Serve stale cache if fetch fails
            if (file_exists($cacheFile)) {
                return json_decode(file_get_contents($cacheFile), true) ?: [];
            }
            return ['error' => 'Unable to fetch RSS feed.'];
        }

        $parsed = $this->parseXml($xmlContent);

        // Save to cache
        file_put_contents($cacheFile, json_encode($parsed, JSON_PRETTY_PRINT));

        return $parsed;
    }

    private function fetchXml(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Modern-RSS-Reader/1.0',
            CURLOPT_SSL_VERIFYPEER => false // Adjust according to your SSL setup
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $data) ? $data : null;
    }

    private function parseXml(string $rawXml): array
    {
        // Suppress XML lib warnings for malformed feeds
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($rawXml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            libxml_clear_errors();
            return ['error' => 'Failed to parse RSS XML structure.'];
        }

        $channel = $xml->channel;
        $result = [
            'title'       => (string)$channel->title,
            'link'        => (string)$channel->link,
            'description' => (string)$channel->description,
            'items'       => []
        ];

        foreach ($channel->item as $entry) {
            // Extract namespaces
            $namespaces = $entry->getNamespaces(true);
            $contentNs  = isset($namespaces['content']) ? $entry->children($namespaces['content']) : null;
            $mediaNs    = isset($namespaces['media'])   ? $entry->children($namespaces['media'])   : null;

            $thumbnail = null;

            // 1. Check <enclosure url="..."> (used by RT, podcasts, standard RSS)
            if (isset($entry->enclosure) && !empty($entry->enclosure['url'])) {
                $thumbnail = (string)$entry->enclosure['url'];
            }

            // 2. Check <media:thumbnail url="..."> (used by BBC, Yahoo, Reuters)
            if (!$thumbnail && $mediaNs && isset($mediaNs->thumbnail)) {
                // If multiple thumbnails are provided, grab the highest resolution or the last one
                $count = count($mediaNs->thumbnail);
                $thumbnail = (string)$mediaNs->thumbnail[$count - 1]->attributes()['url'];
            }

            // 3. Check <media:content url="..."> (used by CNN, Al Jazeera, etc.)
            if (!$thumbnail && $mediaNs && isset($mediaNs->content)) {
                $contentAttr = $mediaNs->content->attributes();
                if (!empty($contentAttr['url'])) {
                    $thumbnail = (string)$contentAttr['url'];
                }
            }

            // 4. Fallback: Parse <img> src from <description> or <content:encoded>
            if (!$thumbnail) {
                $htmlSources = (string)$entry->description . ($contentNs ? (string)$contentNs->encoded : '');
                if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $htmlSources, $matches)) {
                    $thumbnail = $matches[1];
                }
            }

            // Clean description
            $cleanDescription = strip_tags((string)$entry->description);

            $result['items'][] = [
                'title'       => trim((string)$entry->title),
                'link'        => trim((string)$entry->link),
                'pubDate'     => (string)$entry->pubDate,
                'timestamp'   => strtotime((string)$entry->pubDate) ?: time(),
                'description' => $cleanDescription,
                'content'     => $contentNs ? (string)$contentNs->encoded : '',
                'image'       => $thumbnail,
                'guid'        => (string)$entry->guid
            ];
        }

        libxml_clear_errors();
        return $result;
    }
}
