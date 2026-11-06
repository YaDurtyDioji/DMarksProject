<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/Logger.php';

class Recipients {
    public static function all() {
        return db()->query('SELECT * FROM recipients ORDER BY name, id')->fetchAll();
    }
    public static function create($name, $email, $phone) {
        db()->prepare('INSERT INTO recipients (name,email,phone) VALUES (?,?,?)')->execute([$name, $email, $phone]);
        $id = db()->lastInsertId();
        Logger::edit('recipients', $id, 'create', $email);
        return $id;
    }
    public static function update($id, $name, $email, $phone) {
        db()->prepare('UPDATE recipients SET name=?,email=?,phone=? WHERE id=?')->execute([$name, $email, $phone, $id]);
        Logger::edit('recipients', $id, 'update', $email);
    }
    public static function delete($id) {
        db()->prepare('DELETE FROM recipients WHERE id=?')->execute([$id]);
        Logger::edit('recipients', $id, 'delete', '');
    }
    public static function importFromApi($url) {
        if (!$url) throw new Exception('No API URL configured');
        $ctx = stream_context_create(['http' => ['timeout' => 10]]);
        $json = @file_get_contents($url, false, $ctx);
        if (!$json) throw new Exception('API did not return data');
        $data = json_decode($json, true);
        if (!is_array($data)) throw new Exception('API returned invalid JSON');
        $count = 0;
        $exists = db()->prepare('SELECT id FROM recipients WHERE email=?');
        $insert = db()->prepare('INSERT INTO recipients (name,email,phone) VALUES (?,?,?)');
        foreach ($data as $r) {
            $name = $r['name'] ?? ''; $email = $r['email'] ?? ''; $phone = $r['phone'] ?? '';
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $exists->execute([$email]);
            if ($exists->fetch()) continue;
            $insert->execute([$name, $email, $phone]);
            $count++;
        }
        Logger::edit('recipients', 0, 'import', "$count rows from API");
        return $count;
    }
    public static function importCsv($fileField) {
        if (empty($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) throw new Exception('No file');
        $fh = fopen($_FILES[$fileField]['tmp_name'], 'r');
        $count = 0;
        $exists = db()->prepare('SELECT id FROM recipients WHERE email=?');
        $insert = db()->prepare('INSERT INTO recipients (name,email,phone) VALUES (?,?,?)');
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 2) continue;
            $name = trim($row[0]); $email = trim($row[1]); $phone = trim($row[2] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $exists->execute([$email]);
            if ($exists->fetch()) continue;
            $insert->execute([$name, $email, $phone]);
            $count++;
        }
        fclose($fh);
        Logger::edit('recipients', 0, 'import', "$count rows from CSV");
        return $count;
    }
}