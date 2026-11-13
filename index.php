<?php
// index.php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/RssParser.php';

$parser = new RssParser($config);

// Default to 'all' unless a specific valid feed is selected in the URL
$activeKey = isset($_GET['feed']) && (array_key_exists($_GET['feed'], $config['feeds']) || $_GET['feed'] === 'all')
    ? $_GET['feed']
    : 'all';

$allItems  = [];
$errors    = [];
$pageTitle = 'All News';
$pageDesc  = 'Combined live feed from all news sources';

if ($activeKey === 'all') {
    foreach ($config['feeds'] as $key => $source) {
        $data = $parser->getFeed($key);

        if (!empty($data['error'])) {
            $errors[] = $source['name'] . ': ' . $data['error'];
            continue;
        }

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $item['source_name'] = $source['name'];
                $allItems[] = $item;
            }
        }
    }

    // Sort all combined articles by timestamp (newest first)
    usort($allItems, function ($a, $b) {
        return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
    });
} else {
    $data      = $parser->getFeed($activeKey);
    $pageTitle = $data['title'] ?? $config['feeds'][$activeKey]['name'];
    $pageDesc  = $data['description'] ?? 'Single channel feed';

    if (!empty($data['error'])) {
        $errors[] = $data['error'];
    } elseif (!empty($data['items'])) {
        foreach ($data['items'] as $item) {
            $item['source_name'] = $config['feeds'][$activeKey]['name'];
            $allItems[] = $item;
        }
    }
}

// --- Pagination Logic (60 items per page) ---
$perPage     = 60;
$totalItems  = count($allItems);
$totalPages  = max(1, (int) ceil($totalItems / $perPage));
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, (int) $_GET['page'])) : 1;
$offset      = ($currentPage - 1) * $perPage;

// Slice out only the 60 items for the current page
$pagedItems  = array_slice($allItems, $offset, $perPage);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 2.5rem 0 1rem;
        }

        .page-btn {
            padding: 0.5rem 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            text-decoration: none;
            color: inherit;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.15s, color 0.15s;
        }

        .page-btn:hover {
            background: #e2e8f0;
        }

        .page-btn.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
            pointer-events: none;
        }

        .page-btn.disabled {
            opacity: 0.45;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <div>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">
                    <?= htmlspecialchars($pageDesc) ?> &bull; Showing <?= count($pagedItems) ?> of <?= $totalItems ?> articles (Page <?= $currentPage ?> of <?= $totalPages ?>)
                </p>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <form method="GET">
                    <select name="feed" class="feed-select" onchange="this.form.submit()">
                        <option value="all" <?= $activeKey === 'all' ? 'selected' : '' ?>>All Sources</option>
                        <?php foreach ($config['feeds'] as $key => $source): ?>
                            <option value="<?= $key ?>" <?= $key === $activeKey ? 'selected' : '' ?>>
                                <?= htmlspecialchars($source['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <a href="add-source.php" style="padding: 0.5rem 0.9rem; background: #2563eb; color: #fff; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: 500;">
                    + Add Source
                </a>
            </div>

        </header>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
                <p style="color: #ef4444; margin-bottom: 0.5rem;"><?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="grid">
            <?php foreach ($pagedItems as $item): ?>
                <article class="card">
                    <?php if (!empty($item['image'])): ?>
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt="" class="card-img" loading="lazy">
                    <?php endif; ?>
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                            <span style="font-size: 0.75rem; font-weight: 600; color: #2563eb; background: #eff6ff; padding: 2px 8px; border-radius: 4px;">
                                <?= htmlspecialchars($item['source_name']) ?>
                            </span>
                            <span class="card-date"><?= date('M j, Y — H:i', $item['timestamp']) ?></span>
                        </div>
                        <h2 class="card-title">
                            <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                        </h2>
                        <p class="card-desc">
                            <?= htmlspecialchars(mb_strimwidth($item['description'], 0, 160, '...')) ?>
                        </p>
                        <a href="<?= htmlspecialchars($item['link']) ?>" class="card-link" target="_blank" rel="noopener noreferrer">
                            Read Article &rarr;
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Pagination">
                <a class="page-btn <?= $currentPage <= 1 ? 'disabled' : '' ?>"
                    href="?feed=<?= urlencode($activeKey) ?>&page=<?= $currentPage - 1 ?>">
                    &larr; Previous
                </a>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a class="page-btn <?= $p === $currentPage ? 'active' : '' ?>"
                        href="?feed=<?= urlencode($activeKey) ?>&page=<?= $p ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <a class="page-btn <?= $currentPage >= $totalPages ? 'disabled' : '' ?>"
                    href="?feed=<?= urlencode($activeKey) ?>&page=<?= $currentPage + 1 ?>">
                    Next &rarr;
                </a>
            </nav>
        <?php endif; ?>
    </div>
</body>

</html>
