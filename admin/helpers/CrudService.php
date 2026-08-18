<?php
/**
 * admin/helpers/CrudService.php
 * ------------------------------------------------------------
 * Generic, level-agnostic CRUD + secure file upload helpers for
 * the admin content modules.
 *
 * Provides:
 *   - CrudService::insert($table, $data)      — prepared INSERT
 *   - CrudService::update($table, $id, $data) — prepared UPDATE
 *   - CrudService::delete($table, $id)        — prepared DELETE
 *   - CrudService::find($table, $id)          — single row
 *   - CrudService::handleUpload($file, $dir)  — secure image upload
 *
 * SECURITY: table name comes from a fixed allow-list (never user
 * input). All values are bound via PDO prepared statements.
 * Uploads validate real MIME with finfo, cap size, randomise names.
 * ------------------------------------------------------------
 */

class CrudService
{
    /** Allow-list of tables the admin may write to. */
    private const ALLOWED_TABLES = [
        'hotels', 'hotel_rooms', 'weroad_trips', 'events', 'reviews',
        'faq', 'partners', 'services', 'media', 'transfers_locations',
        'transfer_services', 'car_classes', 'routes', 'transfer_extras',
        'hotel_reviews', 'weroad_reviews', 'uses' => 'users',
        // Homepage CMS
        'banners', 'highlights', 'trust_scores', 'awards', 'night_offerings', 'social_links',
        // Tour sub-entities
        'weroad_trip_itineraries', 'weroad_trip_inclusions', 'weroad_trip_optional_activities',
        'weroad_departures', 'weroad_trip_faqs', 'weroad_reviews',
        'tour_categories', 'tour_destinations',
        // Hotel sub-entities
        'hotel_facilities', 'hotel_offers', 'hotel_amenities',
        // SEO + Translations + Site content
        'seo_meta', 'translations', 'menu_items', 'pages', 'page_sections', 'destinations',
    ];

    /**
     * Resolve a table name against the allow-list, else throw.
     */
    public static function table(string $table): string
    {
        $map = self::ALLOWED_TABLES;
        if (isset($map[$table])) {
            return $map[$table];
        }
        if (in_array($table, $map, true)) {
            return $table;
        }
        throw new InvalidArgumentException('Unsafe table name blocked.');
    }

    /**
     * Insert a row. Returns the new id (or 0 on failure).
     */
    public static function insert(string $table, array $data): int
    {
        $table = self::table($table);
        if (!$data) {
            return 0;
        }
        $cols = array_keys($data);
        $ph   = ':' . implode(', :', $cols);
        $sql  = "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES ({$ph})";
        $pdo  = getPDO();
        $stmt = $pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return (int)$pdo->lastInsertId();
    }

    /**
     * Update a row by id. Returns affected bool.
     */
    public static function update(string $table, int $id, array $data): bool
    {
        $table = self::table($table);
        if (!$data || $id <= 0) {
            return false;
        }
        $set = [];
        foreach (array_keys($data) as $k) {
            $set[] = "`{$k}` = :{$k}";
        }
        $sql = "UPDATE `{$table}` SET " . implode(', ', $set) . " WHERE id = :id";
        $pdo = getPDO();
        $stmt = $pdo->prepare($sql);
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Delete a row by id.
     */
    public static function delete(string $table, int $id): bool
    {
        $table = self::table($table);
        if ($id <= 0) {
            return false;
        }
        $pdo = getPDO();
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Fetch a single row by id.
     */
    public static function find(string $table, int $id): ?array
    {
        $table = self::table($table);
        if ($id <= 0) {
            return null;
        }
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Change a boolean-ish status flag (is_active / featured).
     */
    public static function setFlag(string $table, int $id, string $column, int $value): bool
    {
        $table = self::table($table);
        if (!in_array($column, ['is_active', 'featured', 'is_cover'], true)) {
            return false;
        }
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = :v WHERE id = :id");
        $stmt->bindValue(':v', $value ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Securely upload an image into the given web directory.
     * Validates real MIME, size, sanitises the name, randomises it.
     *
     * @return string|null relative path (e.g. uploads/x/abc.jpg) or null on error
     */
    public static function handleUpload(array $file, string $subdir = 'content', int $maxBytes = 4 * 1024 * 1024): ?string
    {
        if (empty($file['name']) || empty($file['tmp_name']) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if ((int)$file['size'] > $maxBytes) {
            return null;
        }

        // Validate real MIME (not just extension).
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        if (!isset($allowed[$mime])) {
            return null;
        }
        $ext = $allowed[$mime];

        $dir = dirname(__DIR__, 2) . '/uploads/' . $subdir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return null;
        }

        $filename = $subdir . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }
        return 'uploads/' . $subdir . '/' . $filename;
    }
}
