<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class Services {
    public static function all() {
        return db()->query('SELECT * FROM services ORDER BY sort_order, id')->fetchAll();
    }
    public static function get($id) {
        $s = db()->prepare('SELECT * FROM services WHERE id=?');
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) return null;
        $row['images'] = self::images($id);
        $row['buttons'] = json_decode($row['buttons_json'] ?: '[]', true) ?: [];
        return $row;
    }
    public static function create($data) {
        db()->prepare('INSERT INTO services (menu_title,slug,content_html,video_url,buttons_json,sort_order) VALUES (?,?,?,?,?,?)')
            ->execute([$data['menu_title'], $data['slug'], $data['content_html'], $data['video_url'], json_encode($data['buttons'] ?? []), $data['sort_order'] ?? 0]);
        $id = db()->lastInsertId();
        Logger::edit('services', $id, 'create', $data['menu_title']);
        return $id;
    }
    public static function update($id, $data) {
        db()->prepare('UPDATE services SET menu_title=?, slug=?, content_html=?, video_url=?, buttons_json=?, sort_order=? WHERE id=?')
            ->execute([$data['menu_title'], $data['slug'], $data['content_html'], $data['video_url'], json_encode($data['buttons'] ?? []), $data['sort_order'] ?? 0, $id]);
        Logger::edit('services', $id, 'update', $data['menu_title']);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM services WHERE id=?')->execute([$id]);
        Logger::edit('services', $id, 'delete', '');
    }
    public static function addImage($serviceId, $fileField, $alt = '', $order = 0) {
        $name = save_upload($fileField);
        if (!$name) return null;
        db()->prepare('INSERT INTO service_images (service_id,image_path,alt_text,sort_order) VALUES (?,?,?,?)')
            ->execute([$serviceId, $name, $alt, $order]);
        $id = db()->lastInsertId();
        Logger::edit('service_images', $id, 'create', $name);
        return $id;
    }
    public static function deleteImage($id) {
        db()->prepare('DELETE FROM service_images WHERE id=?')->execute([$id]);
        Logger::edit('service_images', $id, 'delete', '');
    }
    public static function reorderImages($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE service_images SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function images($id) {
        $s = db()->prepare('SELECT * FROM service_images WHERE service_id=? ORDER BY sort_order, id');
        $s->execute([$id]);
        return $s->fetchAll();
    }
    public static function publicList() {
        $out = [];
        foreach (self::all() as $r) {
            $out[] = [
                'id' => (int)$r['id'],
                'menu_title' => $r['menu_title'],
                'slug' => $r['slug'],
                'content_html' => $r['content_html'],
                'video_url' => $r['video_url'],
                'buttons' => json_decode($r['buttons_json'] ?: '[]', true) ?: [],
                'images' => array_map(fn($i) => ['url' => upload_url($i['image_path']), 'alt' => $i['alt_text']], self::images($r['id'])),
            ];
        }
        return $out;
    }
}