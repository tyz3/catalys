<?php
ini_set('memory_limit', '1024M');
set_time_limit(120);

$dbFile = __DIR__ . '/données/agenda.sqlite';
if (!file_exists($dbFile)) {
    die("❌ Base de données SQLite manquante.");
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);


$tableChoisie = $_GET['table'] ?? $tables[0] ?? null;

$limit = 500;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$total = 0;
$rows = [];
$pages = 1;

if ($tableChoisie) {
    try {
        $total = $pdo->query("SELECT COUNT(*) FROM \"$tableChoisie\"")->fetchColumn();
        $pages = max(1, ceil($total / $limit));
        $stmt = $pdo->query("SELECT * FROM \"$tableChoisie\" LIMIT $limit OFFSET $offset");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $total = 0;
        $rows = [];
        $pages = 1;
    }
}

echo "<h3>📋 Tables dans la base :</h3><ul>";
foreach ($tables as $t) {
    $count = $pdo->query("SELECT COUNT(*) FROM \"$t\"")->fetchColumn();
    echo "<li><strong>" . htmlspecialchars($t) . "</strong> – $count lignes</li>";
}
echo "</ul>";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
      <link rel="stylesheet" href="lire_excel.css">

    <title>Affichage des tables SQLite</title>
</head>
<body>
    <form method="get">
        <label for="table">📂 Choisir une table :</label>
        <select name="table" id="table" onchange="this.form.submit()">
            <?php foreach ($tables as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $t === $tableChoisie ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($rows): ?>
        <h2>🔎 Aperçu de la table « <?= htmlspecialchars($tableChoisie) ?> » (Page <?= $page ?> / <?= $pages ?>)</h2>
                <div class="navigation-pagination">
            <?php if ($page > 1): ?>
                <a href="?table=<?= urlencode($tableChoisie) ?>&page=<?= $page - 1 ?>">← Précédent</a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a href="?table=<?= urlencode($tableChoisie) ?>&page=<?= $page + 1 ?>">Suivant →</a>
            <?php endif; ?>
            </div>

        <table border="1" cellpadding="4" cellspacing="0">
            <thead>
                <tr>
                    <?php foreach (array_keys($rows[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $val): ?>
                            <td><?= htmlspecialchars($val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        
    <?php elseif ($tableChoisie): ?>
        <p style="color:red;">Aucune donnée dans cette table.</p>
    <?php endif; ?>
</body>
</html>
