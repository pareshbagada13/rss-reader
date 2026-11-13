# PHP RSS News Aggregator

A fast, lightweight, database-free RSS news reader built in plain PHP. It aggregates multiple RSS feeds into a unified, chronologically sorted card layout with local file caching, 60-item pagination, and a browser-based source manager that updates `config.php` directly.

## Features

- **Unified Multi-Source Feed:** Combines articles from all configured RSS channels into a single timeline sorted from newest to oldest, with source badges on every card.
- **Channel Filtering:** Switch between **All Sources** or filter down to an individual publisher via the header dropdown.
- **File-Based Caching:** Caches parsed feed data locally (`cache/` directory) for 15 minutes (configurable) to keep page loads fast and avoid rate-limiting from upstream news servers.
- **Pagination:** Displays 60 articles per page with filter-aware Previous/Next and numbered navigation controls.
- **Web-Based Source Manager (`add-source.php`):** Add new RSS feeds or remove existing ones through a clean UI that safely serializes and updates `config.php` with file locking (`LOCK_EX`).
- **Zero Database Required:** Runs entirely on flat PHP files—no MySQL, SQLite, or Composer dependencies needed.

## Project Structure

```text
├── index.php        # Main news grid, feed merger, channel filter, and pagination
├── add-source.php   # Web UI to add/remove RSS feeds and rewrite config.php
├── config.php       # App configuration (cache duration, cache path, RSS feed URLs)
├── RssParser.php    # RSS fetching, XML parsing, and local caching logic
├── style.css        # Responsive card grid and UI styling
└── cache/           # Auto-generated directory storing cached feed responses
