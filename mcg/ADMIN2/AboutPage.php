<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class AboutPage {
    public static function get() {
        $row = db()->query('SELECT * FROM about_page LIMIT 1')->fetch();
        if (!$row) {
            db()->exec("INSERT INTO about_page (content_html) VALUES ('')");
            $row = db()->query('SELECT * FROM about_page LIMIT 1')->fetch();
        }
        $row['media'] = db()->query('SELECT * FROM about_media ORDER BY sort_order, id')->fetchAll();
        return $row;
    }
    public static function update($content, $video) {
        $row = self::get();
        db()->prepare('UPDATE about_page SET content_html=?, video_url=? WHERE id=?')->execute([$content, $video, $row['id']]);
        Logger::edit('about_page', $row['id'], 'update', '');
    }
    public static function addMedia($fileField) {
        $name = save_upload($fileField);
        if (!$name) throw new Exception('No file');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = in_array($ext, ['mp4','webm','ogg']) ? 'video' : 'image';
        db()->prepare('INSERT INTO about_media (media_type,file_path) VALUES (?,?)')->execute([$type, $name]);
        Logger::edit('about_media', db()->lastInsertId(), 'create', $name);
    }
    public static function deleteMedia($id) {
        db()->prepare('DELETE FROM about_media WHERE id=?')->execute([$id]);
        Logger::edit('about_media', $id, 'delete', '');
    }
    public static function reorderMedia($order) {
        foreach ($order as $i => $id) db()->prepare('UPDATE about_media SET sort_order=? WHERE id=?')->execute([$i, $id]);
    }
    public static function publicData() {
        $row = self::get();
        return [
            'content_html' => $row['content_html'],
            'video_url' => $row['video_url'],
            'media' => array_map(fn($m) => ['type'=>$m['media_type'], 'url'=>upload_url($m['file_path'])], $row['media']),
        ];
    }
}