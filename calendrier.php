<?php
session_start();
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/données/agenda.sqlite');
} catch (PDOException $e) {
    die("Erreur connexion base : " . $e->getMessage());
}
$stmt = $pdo->query('SELECT DISTINCT conseiller FROM seances WHERE conseiller IS NOT NULL AND conseiller != "" ORDER BY conseiller');
$conseillers = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($conseillers)) {
    die("Aucun conseiller trouvé en base.");
}
$conseillerSelectionne = $_GET['conseiller'] ?? $_POST['conseiller'] ?? $conseillers[0];
if (!isset($_SESSION['evenements'])) {
    $_SESSION['evenements'] = [];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? null;
    $evenement = trim($_POST['evenement'] ?? '');
    $heureDebut = $_POST['heuredebut'] ?? '';
    $heureFin = $_POST['heurefin'] ?? '';
    $delete = $_POST['delete'] ?? null;
    $conseiller = $_POST['conseiller'] ?? $conseillerSelectionne;
    if ($date && $conseiller && $evenement !== '' && $delete === null) {
        $_SESSION['evenements'][$conseiller][$date][] = [
            'heuredebut' => $heureDebut,
            'heurefin' => $heureFin,
            'texte' => $evenement,
        ];
    }
    if ($date && $conseiller && $delete !== null && isset($_SESSION['evenements'][$conseiller][$date][$delete])) {
        unset($_SESSION['evenements'][$conseiller][$date][$delete]);
        $_SESSION['evenements'][$conseiller][$date] = array_values($_SESSION['evenements'][$conseiller][$date]);
        if (count($_SESSION['evenements'][$conseiller][$date]) === 0) {
            unset($_SESSION['evenements'][$conseiller][$date]);
        }
    }
}
$semaine = (int)($_GET['semaine'] ?? 0);
$ajd = new DateTime();
if (!empty($_GET['dateChoisie'])) {
    $dateChoisie = DateTime::createFromFormat('Y-m-d', $_GET['dateChoisie']);
    if ($dateChoisie) {
        $ajd = clone $dateChoisie;
    }
}
$ajd->modify("{$semaine} weeks");
$ajd->modify('-' . ($ajd->format('N') - 1) . ' days');
$jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
$debutSemaine = $ajd->format('Y-m-d');
$finSemaine = (clone $ajd)->modify('+4 days')->format('Y-m-d');
$sql = "SELECT date, heuredebut, heurefin, objet FROM seances 
        WHERE conseiller = :conseiller 
          AND date(date) BETWEEN :debutSemaine AND :finSemaine
          AND (seanceannulee IS NULL OR seanceannulee = 0)
          AND objet IS NOT NULL AND objet != ''";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':conseiller' => $conseillerSelectionne,
    ':debutSemaine' => $debutSemaine,
    ':finSemaine' => $finSemaine,
]);
$evenementsBase = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dateKey = substr($row['date'], 0, 10);
    if (!isset($evenementsBase[$dateKey])) {
        $evenementsBase[$dateKey] = [];
    }
    $evenementsBase[$dateKey][] = [
        'heuredebut' => $row['heuredebut'],
        'heurefin' => $row['heurefin'],
        'texte' => $row['objet'],
    ];
}
function heureToMinutes(string $heure): int {
    $parts = explode(':', $heure);
    return ((int)($parts[0] ?? 0) * 60) + ((int)($parts[1] ?? 0));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Agenda de <?= htmlspecialchars($conseillerSelectionne) ?></title>
    <link rel="stylesheet" href="calendrier.css" />
</head>

<body>

<div class="calendar-container">

    <form method="GET" style="margin-bottom: 1em;">
        <label for="conseiller">Choisir un conseiller :</label>
        <select name="conseiller" id="conseiller" onchange="this.form.submit()">
            <?php foreach ($conseillers as $conseiller): ?>
                <option value="<?= htmlspecialchars($conseiller) ?>" <?= $conseiller === $conseillerSelectionne ? 'selected' : '' ?>>
                    <?= htmlspecialchars($conseiller) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="semaine" value="<?= $semaine ?>" />
    </form>

    <form method="GET" style="margin-bottom: 1em;">
        <input type="hidden" name="conseiller" value="<?= htmlspecialchars($conseillerSelectionne) ?>" />
        <label for="dateChoisie">Choisir une date :</label>
        <input type="date" id="dateChoisie" name="dateChoisie" value="<?= (isset($_GET['dateChoisie']) ? htmlspecialchars($_GET['dateChoisie']) : '') ?>" />
        <button type="submit">Voir la semaine</button>
    </form>

    <h2>Agenda de <?= htmlspecialchars($conseillerSelectionne) ?> - Semaine du <?= $ajd->format('d/m/Y') ?></h2>

    <div class="navigation">
        <a href="?conseiller=<?= urlencode($conseillerSelectionne) ?>&semaine=<?= $semaine - 1 ?>">← Semaine précédente</a>
        <a href="?conseiller=<?= urlencode($conseillerSelectionne) ?>&semaine=0">Aujourd'hui</a>
        <a href="?conseiller=<?= urlencode($conseillerSelectionne) ?>&semaine=<?= $semaine + 1 ?>">Semaine suivante →</a>
    </div>

    <table class="calendar">
        <thead>
            <tr>
                <th class="heure-col"></th>
                <?php for ($i = 0; $i < 5; $i++):
                    $jour = (clone $ajd)->modify("+$i days"); ?>
                    <th class="jour-col"><?= $jours[$i] ?><br><?= $jour->format('d/m') ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="heure-col">
                    <?php for ($h = 8; $h <= 20; $h++): ?>
                        <div style="height:60px; line-height:60px;"><?= $h ?>:00</div>
                    <?php endfor; ?>
                </td>
                <?php for ($i = 0; $i < 5; $i++):
                    $jour = (clone $ajd)->modify("+$i days");
                    $d = $jour->format('Y-m-d'); ?>
                    <td class="jour-col">

                        <?php
                        $evenementsAffiches = [];

                        if (isset($evenementsBase[$d])) {
                            foreach ($evenementsBase[$d] as $ev) {
                                $evenementsAffiches[] = $ev;
                            }
                        }

                        if (isset($_SESSION['evenements'][$conseillerSelectionne][$d])) {
                            foreach ($_SESSION['evenements'][$conseillerSelectionne][$d] as $ev) {
                                $evenementsAffiches[] = $ev;
                            }
                        }

                        usort($evenementsAffiches, fn($a, $b) => heureToMinutes($a['heuredebut']) <=> heureToMinutes($b['heuredebut']));

                        foreach ($evenementsAffiches as $index => $ev):
                            $debut = $ev['heuredebut'] ?? '08:00';
                            $fin = $ev['heurefin'] ?? '09:00';
                            $top = (heureToMinutes($debut) - 480);
                            $height = max(heureToMinutes($fin) - heureToMinutes($debut), 30);
                            ?>
                            <div class="evenement"
                                 style="top:<?= $top ?>px; height:<?= $height ?>px; cursor:pointer;"
                                 onclick='afficherPopup(<?= json_encode([
                                     'date' => $d,
                                     'heuredebut' => $debut,
                                     'heurefin' => $fin,
                                     'objet' => $ev['texte'],
                                     'conseiller' => $conseillerSelectionne,
                                 ], JSON_HEX_APOS | JSON_UNESCAPED_UNICODE) ?>)'>
                                <strong><?= htmlspecialchars($debut . ' - ' . $fin) ?></strong><br>
                                <?= htmlspecialchars($ev['texte']) ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                <?php endfor; ?>
            </tr>
        </tbody>
    </table>
</div>

<div id="popup" class="popup-overlay">
    <div class="popup-content">
        <span class="close-btn">×</span>
        <h3>Détail de l'événement</h3>
        <div id="popup-details"></div>
    </div>
</div>

<script>
function afficherPopup(data) {
    const popup = document.getElementById('popup');
    const details = document.getElementById('popup-details');
    details.innerHTML = `
        <p><strong>Objet :</strong> ${data.objet || ''}</p>
        <p><strong>Date :</strong> ${data.date || ''}</p>
        <p><strong>Heure :</strong> ${data.heuredebut || ''} - ${data.heurefin || ''}</p>
        <p><strong>Conseiller :</strong> ${data.conseiller || ''}</p>
    `;
    popup.style.display = 'flex';
}
document.querySelector('#popup .close-btn').addEventListener('click', () => {
    document.getElementById('popup').style.display = 'none';
});
document.getElementById('popup').addEventListener('click', e => {
    if (e.target.id === 'popup') {
        e.target.style.display = 'none';
    }
});
</script>

</body>
</html>
