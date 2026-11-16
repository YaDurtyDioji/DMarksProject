<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class Gallery {
    public static function categories() {
        return db()->query('SELECT * FROM gallery_categories ORDER BY sort_order, id')->fetchAll();
    }
    public static function createCategory($name) {
        db()->prepare('INSERT INTO gallery_categories (name,sort_order) VALUES (?,999)')->execute([$name]);
        $id = db()->lastInsertId();
        Logger::edit('gallery_categories', $id, 'create', $name);
        return $id;
    }
    public static function renameCategory($id, $name) {
        db()->prepare('UPDATE gallery_categories SET name=? WHERE id=?')->execute([$name, $id]);
        Logger::edit('gallery_categories', $id, 'update', $name);
    }
    public static function deleteCategory($id) {
        db()->prepare('DELETE FROM gallery_categories WHERE id=?')->execute([$id]);
        Logger::edit('gallery_categories', $id, 'delete', '');
    }
    public static function reorderCategories($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE gallery_categories SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function items($catId) {
        $s = db()->prepare('SELECT * FROM gallery_items WHERE category_id=? ORDER BY sort_order, id');
        $s->execute([$catId]);
        return $s->fetchAll();
    }
    public static function addItem($catId, $caption, $fileField) {
        $name = save_upload($fileField);
        if (!$name) throw new Exception('No file uploaded');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = in_array($ext, ['mp4','webm','ogg']) ? 'video' : 'image';
        db()->prepare('INSERT INTO gallery_items (category_id,media_type,file_path,caption,sort_order) VALUES (?,?,?,?,999)')
            ->execute([$catId, $type, $name, $caption]);
        $id = db()->lastInsertId();
        Logger::edit('gallery_items', $id, 'create', $name);
        return $id;
    }
    public static function updateItem($id, $caption) {
        db()->prepare('UPDATE gallery_items SET caption=? WHERE id=?')->execute([$caption, $id]);
        Logger::edit('gallery_items', $id, 'update', $caption);
    }
    public static function deleteItem($id) {
        db()->prepare('DELETE FROM gallery_items WHERE id=?')->execute([$id]);
        Logger::edit('gallery_items', $id, 'delete', '');
    }
    public static function reorderItems($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE gallery_items SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function publicGallery() {
        $out = [];
        foreach (self::categories() as $c) {
            $items = [];
            foreach (self::items($c['id']) as $i) {
                $items[] = ['id'=>(int)$i['id'], 'type'=>$i['media_type'], 'url'=>upload_url($i['file_path']), 'caption'=>$i['caption']];
            }
            $out[] = ['id'=>(int)$c['id'], 'name'=>$c['name'], 'items'=>$items];
        }
        return $out;
    }
}