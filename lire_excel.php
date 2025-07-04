<?php
// augmente limite mémoire et temps max d'exécution pour éviter erreurs sur gros fichiers
ini_set('memory_limit', '1024m');
set_time_limit(120);

// chemin vers la base sqlite
$dbfile = __dir__ . '/données/agenda.sqlite';

// vérifie si la base existe, sinon stop
if (!file_exists($dbfile)) {
    die("❌ base de données sqlite manquante.");
}

// connexion pdo sqlite avec gestion des erreurs
$pdo = new pdo('sqlite:' . $dbfile);
$pdo->setAttribute(pdo::ATTR_ERRMODE, pdo::ERRMODE_EXCEPTION);

// récupère toutes les tables disponibles dans la base
$tables = $pdo->query("select name from sqlite_master where type='table' order by name")->fetchAll(pdo::FETCH_COLUMN);

// table sélectionnée via get, sinon la première disponible ou null
$tablechoisie = $_GET['table'] ?? $tables[0] ?? null;

// pagination : limite, page courante et offset
$limit = 500;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$total = 0;
$rows = [];
$pages = 1;

// si une table est choisie, récupère le nombre total de lignes et les données de la page courante
if ($tablechoisie) {
    try {
        $total = $pdo->query("select count(*) from \"$tablechoisie\"")->fetchColumn();
        $pages = max(1, ceil($total / $limit));
        $stmt = $pdo->query("select * from \"$tablechoisie\" limit $limit offset $offset");
        $rows = $stmt->fetchAll(pdo::FETCH_ASSOC);
    } catch (exception $e) {
        // en cas d'erreur (ex : table inexistante), vide les données
        $total = 0;
        $rows = [];
        $pages = 1;
    }
}

// affiche la liste des tables avec leur nombre de lignes
echo "<h3>📋 tables dans la base :</h3><ul>";
foreach ($tables as $t) {
    $count = $pdo->query("select count(*) from \"$t\"")->fetchColumn();
    echo "<li><strong>" . htmlspecialchars($t) . "</strong> – $count lignes</li>";
}
echo "</ul>";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <link rel="stylesheet" href="lire_excel.css">
    <title>affichage des tables sqlite</title>
</head>
<body>
    <!-- formulaire de sélection de table avec soumission automatique -->
    <form method="get">
        <label for="table">📂 choisir une table :</label>
        <select name="table" id="table" onchange="this.form.submit()">
            <?php foreach ($tables as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>" <?= $t === $tablechoisie ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- affichage de la table sélectionnée avec pagination -->
    <?php if ($rows): ?>
        <h2>🔎 aperçu de la table « <?= htmlspecialchars($tablechoisie) ?> » (page <?= $page ?> / <?= $pages ?>)</h2>
        <div class="navigation-pagination">
            <?php if ($page > 1): ?>
                <a href="?table=<?= urlencode($tablechoisie) ?>&page=<?= $page - 1 ?>">← précédent</a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a href="?table=<?= urlencode($tablechoisie) ?>&page=<?= $page + 1 ?>">suivant →</a>
            <?php endif; ?>
        </div>

        <!-- tableau html affichant les données -->
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
        
    <?php elseif ($tablechoisie): ?>
        <!-- message si table vide -->
        <p style="color:red;">aucune donnée dans cette table.</p>
    <?php endif; ?>
</body>
</html>
