<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class Testimonials {
    public static function all() {
        return db()->query('SELECT * FROM testimonials ORDER BY sort_order, id')->fetchAll();
    }
    public static function create($author, $content, $fileField = null) {
        $img = $fileField ? save_upload($fileField) : null;
        db()->prepare('INSERT INTO testimonials (author,content,image_path,sort_order) VALUES (?,?,?,999)')
            ->execute([$author, $content, $img]);
        $id = db()->lastInsertId();
        Logger::edit('testimonials', $id, 'create', $author);
        return $id;
    }
    public static function update($id, $author, $content, $fileField = null) {
        $img = $fileField ? save_upload($fileField) : null;
        if ($img) {
            db()->prepare('UPDATE testimonials SET author=?, content=?, image_path=? WHERE id=?')->execute([$author, $content, $img, $id]);
        } else {
            db()->prepare('UPDATE testimonials SET author=?, content=? WHERE id=?')->execute([$author, $content, $id]);
        }
        Logger::edit('testimonials', $id, 'update', $author);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM testimonials WHERE id=?')->execute([$id]);
        Logger::edit('testimonials', $id, 'delete', '');
    }
    public static function reorder($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE testimonials SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function publicList() {
        return array_map(fn($t) => [
            'author' => $t['author'],
            'content' => $t['content'],
            'image' => $t['image_path'] ? upload_url($t['image_path']) : null,
        ], self::all());
    }
}