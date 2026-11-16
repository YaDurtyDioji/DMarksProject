<?php
declare(strict_types=1);

function search_exercises(string $q = '', string $bodyPart = '', string $equipment = '', string $category = '', int $limit = 100): array
{
    $pdo = get_db();
    $sql = 'SELECT id, name, body_part, equipment, target, category, demo_url
            FROM exercises WHERE 1=1';
    $params = [];

    if ($q !== '')        { $sql .= ' AND name LIKE :q';    $params[':q']  = '%' . $q . '%'; }
    if ($bodyPart !== '') { $sql .= ' AND body_part = :bp'; $params[':bp'] = $bodyPart; }
    if ($equipment !== ''){ $sql .= ' AND equipment = :eq'; $params[':eq'] = $equipment; }
    if ($category !== '') { $sql .= ' AND category = :cat'; $params[':cat'] = $category; }

    $sql .= ' ORDER BY name LIMIT :lim';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_filter_options(): array
{
    $pdo = get_db();
    return [
        'bodyParts' => $pdo->query("SELECT DISTINCT body_part FROM exercises
                                    WHERE body_part IS NOT NULL AND body_part <> ''
                                    ORDER BY body_part")->fetchAll(PDO::FETCH_COLUMN),
        'equipment' => $pdo->query("SELECT DISTINCT equipment FROM exercises
                                    WHERE equipment IS NOT NULL AND equipment <> ''
                                    ORDER BY equipment")->fetchAll(PDO::FETCH_COLUMN),
        'categories'=> $pdo->query("SELECT DISTINCT category FROM exercises
                                    WHERE category IS NOT NULL AND category <> ''
                                    ORDER BY category")->fetchAll(PDO::FETCH_COLUMN),
    ];
}

/* ---------- routines (scoped to $userId) --------------------------- */

function list_routines(int $userId): array
{
    $stmt = get_db()->prepare(
        'SELECT r.id, r.name, r.created_at,
                COUNT(re.exercise_id) AS exercise_count
         FROM routines r
         LEFT JOIN routine_exercises re ON re.routine_id = r.id
         WHERE r.user_id = ?
         GROUP BY r.id, r.name, r.created_at
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function get_routine(int $id, int $userId): ?array
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT id, name, created_at FROM routines WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $routine = $stmt->fetch();
    if (!$routine) return null;

    $stmt = $pdo->prepare(
        'SELECT e.id, e.name, e.body_part, e.equipment, e.target, e.demo_url,
                re.position, re.sets, re.reps, re.rest_sec
         FROM routine_exercises re
         JOIN exercises e ON e.id = re.exercise_id
         WHERE re.routine_id = ?
         ORDER BY re.position'
    );
    $stmt->execute([$id]);
    $routine['exercises'] = $stmt->fetchAll();

    return $routine;
}

function save_routine(string $name, array $exercises, int $userId, ?int $id = null): int
{
    $pdo = get_db();
    $pdo->beginTransaction();

    try {
        if ($id !== null) {
            // Only allow updating routines the user owns.
            $check = $pdo->prepare('SELECT 1 FROM routines WHERE id = ? AND user_id = ?');
            $check->execute([$id, $userId]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Routine not found');
            }

            $pdo->prepare('UPDATE routines SET name = ? WHERE id = ?')
                ->execute([$name, $id]);
            $pdo->prepare('DELETE FROM routine_exercises WHERE routine_id = ?')
                ->execute([$id]);
            $routineId = $id;
        } else {
            $pdo->prepare('INSERT INTO routines (user_id, name) VALUES (?, ?)')
                ->execute([$userId, $name]);
            $routineId = (int) $pdo->lastInsertId();
        }

        $ins = $pdo->prepare(
            'INSERT INTO routine_exercises (routine_id, exercise_id, position, sets_data, notes)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($exercises as $pos => $ex) {
            $ins->execute([
                $routineId,
                (string) $ex['id'],
                $pos,
                json_encode($ex['sets_data'] ?? []), // Stores the weight/reps/RPE array
                $ex['notes'] ?? null,
            ]);
        }

        $pdo->commit();
        return $routineId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function delete_routine(int $id, int $userId): bool
{
    $stmt = get_db()->prepare('DELETE FROM routines WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    return $stmt->rowCount() > 0;
}