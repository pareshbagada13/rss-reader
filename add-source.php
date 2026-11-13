<?php
// add-source.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$configPath = __DIR__ . '/config.php';
$config     = require $configPath;

$message = '';
$error   = '';

/**
 * Helper: Safely rewrites config.php preserving __DIR__ . '/cache'
 */
function saveConfig(string $filePath, array $config): bool
{
    $feedsCode = "";
    foreach ($config['feeds'] as $key => $feed) {
        $safeKey  = var_export((string) $key, true);
        $safeName = var_export((string) $feed['name'], true);
        $safeUrl  = var_export((string) $feed['url'], true);

        $feedsCode .= "        {$safeKey} => [\n";
        $feedsCode .= "            'name' => {$safeName},\n";
        $feedsCode .= "            'url'  => {$safeUrl}\n";
        $feedsCode .= "        ],\n";
    }

    // Remove trailing comma on last item for clean formatting
    $feedsCode = rtrim($feedsCode, ",\n") . "\n";
    $cacheTime = (int) ($config['cache_time'] ?? 900);

    $phpContent = <<<PHP
<?php
// config.php

return [
    'cache_time' => {$cacheTime}, // 15 minutes cache lifespan
    'cache_dir'  => __DIR__ . '/cache',
    'feeds'      => [
{$feedsCode}    ]
];

PHP;

    return file_put_contents($filePath, $phpContent, LOCK_EX) !== false;
}

// Handle Add Source Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add') {
        $name = trim($_POST['name'] ?? '');
        $url  = trim($_POST['url'] ?? '');
        $customKey = trim($_POST['key'] ?? '');

        if ($name === '' || $url === '') {
            $error = 'Both Source Name and RSS Feed URL are required.';
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $error = 'Please enter a valid RSS URL starting with http:// or https://.';
        } else {
            // Generate a clean slug key from custom input or source name
            $rawKey = $customKey !== '' ? $customKey : $name;
            $slug   = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $rawKey));
            if ($slug === '') {
                $slug = 'feed_' . time();
            }

            // Ensure key is unique
            $finalKey = $slug;
            $counter  = 2;
            while (isset($config['feeds'][$finalKey])) {
                $finalKey = $slug . $counter;
                $counter++;
            }

            $config['feeds'][$finalKey] = [
                'name' => $name,
                'url'  => $url,
            ];

            if (saveConfig($configPath, $config)) {
                $message = "Added \"{$name}\" successfully!";
            } else {
                $error = 'Failed to write to config.php. Check file write permissions.';
            }
        }
    }

    // Handle Delete Source
    if ($_POST['action'] === 'delete' && !empty($_POST['delete_key'])) {
        $delKey = $_POST['delete_key'];
        if (isset($config['feeds'][$delKey])) {
            if (count($config['feeds']) <= 1) {
                $error = 'You must keep at least one RSS feed in config.php.';
            } else {
                $removedName = $config['feeds'][$delKey]['name'];
                unset($config['feeds'][$delKey]);
                if (saveConfig($configPath, $config)) {
                    $message = "Removed \"{$removedName}\".";
                } else {
                    $error = 'Failed to update config.php.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage RSS Sources</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .manager-box {
            max-width: 680px;
            margin: 2rem auto;
            background: #ffffff;
            padding: 1.75rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }
        .form-group {
            margin-bottom: 1.1rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.35rem;
            font-size: 0.92rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.65rem 0.8rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 0.7rem 1.3rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .btn-primary:hover {
            background: #1d4ed8;
        }
        .alert {
            padding: 0.8rem 1rem;
            border-radius: 6px;
            margin-bottom: 1.2rem;
            font-size: 0.92rem;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .source-list {
            margin-top: 2rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 1.25rem;
        }
        .source-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .source-url {
            font-size: 0.82rem;
            color: #64748b;
            word-break: break-all;
        }
        .btn-delete {
            background: #ef4444;
            color: #fff;
            border: none;
            padding: 0.4rem 0.75rem;
            border-radius: 5px;
            font-size: 0.82rem;
            cursor: pointer;
            flex-shrink: 0;
        }
        .btn-delete:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>Manage RSS Sources</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">
                    Add or remove feeds stored in <code>config.php</code>
                </p>
            </div>
            <a href="index.php" class="btn-primary" style="text-decoration: none;">&larr; Back to News</a>
        </header>

        <div class="manager-box">
            <?php if ($message !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label for="name">Source Name *</label>
                    <input type="text" id="name" name="name" placeholder="e.g. News18 Gujarat" required>
                </div>

                <div class="form-group">
                    <label for="url">RSS Feed URL *</label>
                    <input type="url" id="url" name="url" placeholder="https://gujarati.news18.com/commonfeeds/v1/guj/rss/samachar.xml" required>
                </div>

                <div class="form-group">
                    <label for="key">Short Key (Optional)</label>
                    <input type="text" id="key" name="key" placeholder="e.g. news18 (auto-generated if left blank)">
                </div>

                <button type="submit" class="btn-primary">+ Add Source to config.php</button>
            </form>

            <div class="source-list">
                <h3 style="margin-bottom: 0.75rem;">Active Feeds (<?= count($config['feeds']) ?>)</h3>
                <?php foreach ($config['feeds'] as $key => $feed): ?>
                    <div class="source-item">
                        <div>
                            <strong><?= htmlspecialchars($feed['name']) ?></strong>
                            <span style="font-size: 0.78rem; color: #64748b;">(key: <code><?= htmlspecialchars($key) ?></code>)</span>
                            <div class="source-url"><?= htmlspecialchars($feed['url']) ?></div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Remove this RSS source?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="delete_key" value="<?= htmlspecialchars($key) ?>">
                            <button type="submit" class="btn-delete">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>