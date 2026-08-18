<?php
/**
 * admin/helpers/AdminQuery.php
 * ------------------------------------------------------------
 * Reusable helpers for admin listing pages:
 *   - Pagination (page, per_page, offset, total pages)
 *   - Safe sort columns (never interpolate raw input)
 *   - Reusable search WHERE builder
 *   - Common status filter
 *
 * All queries still use the caller's PDO prepared statements.
 * This class only centralises parameter parsing + URL building.
 * ------------------------------------------------------------
 */

class AdminQuery
{
    /**
     * Parse & sanitise pagination input.
     * @return array{page:int, per_page:int, offset:int}
     */
    public static function pagination(int $defaultPer = 15, int $maxPer = 100): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per  = (int)($_GET['per'] ?? $defaultPer);
        if ($per < 1 || $per > $maxPer) {
            $per = $defaultPer;
        }
        return [
            'page'     => $page,
            'per_page' => $per,
            'offset'   => ($page - 1) * $per,
        ];
    }

    /**
     * A validated sort column out of an allow-list.
     * @return array{col:string, dir:string}
     */
    public static function sort(array $allowed, string $defaultCol, string $defaultDir = 'DESC'): array
    {
        $col = (string)($_GET['sort'] ?? $defaultCol);
        if (!in_array($col, $allowed, true)) {
            $col = $defaultCol;
        }
        $dir = strtoupper((string)($_GET['order'] ?? $defaultDir));
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = $defaultDir;
        }
        return ['col' => $col, 'dir' => $dir];
    }

    /**
     * Normalise a search keyword (trim oversized input).
     */
    public static function search(): string
    {
        $q = trim((string)($_GET['q'] ?? ''));
        return mb_substr($q, 0, 100);
    }

    /**
     * Normalise a status filter from an allow-list.
     */
    public static function statusFilter(array $allowed): string
    {
        $s = (string)($_GET['status'] ?? '');
        return in_array($s, $allowed, true) ? $s : '';
    }

    /**
     * Render a sortable header link.
     */
    public static function sortLink($col, $label, array $allowed, string $defaultCol): string
    {
        $sort = self::sort($allowed, $defaultCol);
        $dir  = ($sort['col'] === $col && $sort['dir'] === 'ASC') ? 'DESC' : 'ASC';
        $qs   = array_merge($_GET, ['sort' => $col, 'order' => $dir]);
        $url  = '?' . http_build_query($qs);
        $arrow = $sort['col'] === $col
            ? ($sort['dir'] === 'ASC' ? ' &uarr;' : ' &darr;')
            : '';
        return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . $arrow . '</a>';
    }

    /**
     * Build a URL query preserving current filters while overriding some keys.
     */
    public static function url(array $overrides = []): string
    {
        $qs = array_merge($_GET, $overrides);
        return '?' . http_build_query($qs);
    }

    /**
     * Escape + html helper (alias, kept for brevity in views).
     */
    public static function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
