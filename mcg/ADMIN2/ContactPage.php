<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';

class ContactPage {
    public static function get() {
        $row = db()->query('SELECT * FROM contact_page LIMIT 1')->fetch();
        if (!$row) {
            db()->exec("INSERT INTO contact_page (content_html) VALUES ('')");
            $row = db()->query('SELECT * FROM contact_page LIMIT 1')->fetch();
        }
        return $row;
    }
    public static function update($content) {
        $row = self::get();
        db()->prepare('UPDATE contact_page SET content_html=? WHERE id=?')->execute([$content, $row['id']]);
        Logger::edit('contact_page', $row['id'], 'update', '');
    }
    public static function publicData() { $r = self::get(); return ['content_html' => $r['content_html']]; }
}