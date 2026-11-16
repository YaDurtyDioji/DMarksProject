<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Recipients.php';

class BulkEmail {
    public static function all() {
        return db()->query('SELECT * FROM bulk_emails ORDER BY id DESC')->fetchAll();
    }
    public static function get($id) {
        $s = db()->prepare('SELECT * FROM bulk_emails WHERE id=?');
        $s->execute([$id]);
        $r = $s->fetch();
        if ($r) $r['buttons'] = json_decode($r['buttons_json'] ?: '[]', true) ?: [];
        return $r;
    }
    public static function create($subject, $body, $buttons) {
        db()->prepare('INSERT INTO bulk_emails (subject,body_html,buttons_json) VALUES (?,?,?)')
            ->execute([$subject, $body, json_encode($buttons)]);
        $id = db()->lastInsertId();
        Logger::edit('bulk_emails', $id, 'create', $subject);
        return $id;
    }
    public static function update($id, $subject, $body, $buttons) {
        db()->prepare('UPDATE bulk_emails SET subject=?, body_html=?, buttons_json=? WHERE id=?')
            ->execute([$subject, $body, json_encode($buttons), $id]);
        Logger::edit('bulk_emails', $id, 'update', $subject);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM bulk_emails WHERE id=?')->execute([$id]);
        Logger::edit('bulk_emails', $id, 'delete', '');
    }
    public static function send($id) {
        $email = self::get($id);
        if (!$email) throw new Exception('Not found');
        $recipients = Recipients::all();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: noreply@$host\r\n";
        $sent = 0;
        foreach ($recipients as $r) {
            $body = $email['body_html'];
            foreach ($email['buttons'] as $b) {
                $body .= '<p><a href="' . h($b['href']) . '" style="display:inline-block;padding:10px 20px;background:#007bff;color:#fff;text-decoration:none;border-radius:4px;margin:5px;">' . h($b['text']) . '</a></p>';
            }
            @mail($r['email'], $email['subject'], $body, $headers);
            $sent++;
        }
        db()->prepare('UPDATE bulk_emails SET sent_at=NOW() WHERE id=?')->execute([$id]);
        Logger::edit('bulk_emails', $id, 'send', "sent to $sent");
        return $sent;
    }
}