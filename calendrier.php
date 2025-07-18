<?php
session_start();

try {
    // connexion à la base sqlite
    $pdo = new PDO('sqlite:' . __DIR__ . '/données/agenda.sqlite');
} catch (PDOException $e) {
    die("erreur connexion base : " . $e->getMessage());
}

// récupérer tous les conseillers uniques des deux tables
$sqlConseillers = "
    SELECT DISTINCT conseiller AS conseiller FROM seances WHERE conseiller IS NOT NULL AND conseiller != ''
    UNION
    SELECT DISTINCT referent AS conseiller FROM rdv WHERE referent IS NOT NULL AND referent != ''
    ORDER BY conseiller
";
$stmt = $pdo->query($sqlConseillers);
$conseillers = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($conseillers)) {
    die("aucun conseiller trouvé en base.");
}

// conseiller choisi en get ou post, sinon premier de la liste
$conseillerSelectionne = $_GET['conseiller'] ?? $_POST['conseiller'] ?? $conseillers[0];

// tableau en session pour stocker les événements ajoutés à la volée
if (!isset($_SESSION['evenements'])) {
    $_SESSION['evenements'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // récupérer données du formulaire
    $date = $_POST['date'] ?? null;
    $evenement = trim($_POST['evenement'] ?? '');
    $heureDebut = $_POST['heuredebut'] ?? '';
    $heureFin = $_POST['heurefin'] ?? '';
    $delete = $_POST['delete'] ?? null;
    $conseiller = $_POST['conseiller'] ?? $conseillerSelectionne;

    // ajout d'un événement en session
    if ($date && $conseiller && $evenement !== '' && $delete === null) {
        $_SESSION['evenements'][$conseiller][$date][] = [
            'heuredebut' => $heureDebut,
            'heurefin' => $heureFin,
            'texte' => $evenement,
        ];
    }
    // suppression d'un événement en session
    if ($date && $conseiller && $delete !== null && isset($_SESSION['evenements'][$conseiller][$date][$delete])) {
        unset($_SESSION['evenements'][$conseiller][$date][$delete]);
        // réindexer le tableau
        $_SESSION['evenements'][$conseiller][$date] = array_values($_SESSION['evenements'][$conseiller][$date]);
        // supprimer la date si vide
        if (count($_SESSION['evenements'][$conseiller][$date]) === 0) {
            unset($_SESSION['evenements'][$conseiller][$date]);
        }
    }
}

// gérer la semaine affichée (0 = semaine actuelle)
$semaine = (int)($_GET['semaine'] ?? 0);
$ajd = new DateTime();

if (!empty($_GET['dateChoisie'])) {
    $dateChoisie = DateTime::createFromFormat('Y-m-d', $_GET['dateChoisie']);
    if ($dateChoisie) {
        $ajd = clone $dateChoisie;
    }
}

// ajuster la date selon la semaine choisie
$ajd->modify("{$semaine} weeks");
// reculer au lundi de la semaine
$ajd->modify('-' . ($ajd->format('N') - 1) . ' days');

$jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
$debutSemaine = $ajd->format('Y-m-d');
$finSemaine = (clone $ajd)->modify('+4 days')->format('Y-m-d');

// requête pour récupérer les événements du conseiller cette semaine
$sql = "
    SELECT 
        'seance' AS source,
        conseiller,
        date(date) AS date,
        heuredebut,
        heurefin,
        typecontact AS type,
        typesautres AS typage,
        seanceannulee AS absence,
        objet,
        lieu,
        NULL AS present,
        NULL AS motif_absence,
        NULL AS site
    FROM seances
    WHERE conseiller = :conseiller
      AND date(date) BETWEEN :debutSemaine AND :finSemaine
      AND (seanceannulee IS NULL OR seanceannulee = 0)
      AND objet IS NOT NULL AND objet != ''

    UNION ALL

    SELECT
        'rdv' AS source,
        referent AS conseiller,
        date(date_deb) AS date,
        strftime('%H:%M', date_deb) AS heuredebut,
        strftime('%H:%M', date_fin) AS heurefin,
        type AS type,
        typage AS typage,
        CASE WHEN present = 0 THEN 1 ELSE 0 END AS absence,
        objet,
        site AS lieu,
        present,
        motif_absence,
        site
    FROM rdv
    WHERE referent = :conseiller
      AND date(date_deb) BETWEEN :debutSemaine AND :finSemaine
      AND objet IS NOT NULL AND objet != ''
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':conseiller' => $conseillerSelectionne,
    ':debutSemaine' => $debutSemaine,
    ':finSemaine' => $finSemaine,
]);

$evenementsBase = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dateKey = $row['date'];
    if (!isset($evenementsBase[$dateKey])) {
        $evenementsBase[$dateKey] = [];
    }
    $evenementsBase[$dateKey][] = $row;
}

// convertit heure 'HH:MM' en minutes depuis 00:00
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

    <!-- Formulaire pour choisir le conseiller affiché -->
    <form method="GET" style="margin-bottom: 1em;">
        <label for="conseiller">Choisir un conseiller :</label>
        <select name="conseiller" id="conseiller" onchange="this.form.submit()">
            <?php foreach ($conseillers as $conseiller): ?>
                <option value="<?= htmlspecialchars($conseiller) ?>" <?= $conseiller === $conseillerSelectionne ? 'selected' : '' ?>>
                    <?= htmlspecialchars($conseiller) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <!-- On conserve la semaine affichée lors du changement de conseiller -->
        <input type="hidden" name="semaine" value="<?= $semaine ?>" />
    </form>

    <!-- Formulaire pour choisir une date précise afin d'afficher la semaine correspondante -->
    <form method="GET" style="margin-bottom: 1em;">
        <input type="hidden" name="conseiller" value="<?= htmlspecialchars($conseillerSelectionne) ?>" />
        <label for="dateChoisie">Choisir une date :</label>
        <input type="date" id="dateChoisie" name="dateChoisie" value="<?= (isset($_GET['dateChoisie']) ? htmlspecialchars($_GET['dateChoisie']) : '') ?>" />
        <button type="submit">Voir la semaine</button>
    </form>

    <!-- Titre affichant le conseiller sélectionné et la date du lundi de la semaine -->
    <h2>Agenda de <?= htmlspecialchars($conseillerSelectionne) ?> - Semaine du <?= $ajd->format('d/m/Y') ?></h2>

    <!-- Navigation entre les semaines -->
    <div class="navigation">
        <a href="?conseiller=<?= urlencode($conseillerSelectionne) ?>&semaine=<?= $semaine - 1 ?>">← Semaine précédente</a>
        <a href="?conseiller=<?= urlencode($conseillerSelectionne) ?>&semaine=0">Aujourd'hui</a>
        <a href="?conseiller=<?= urlencode($conseillerSelectionne) ?>&semaine=<?= $semaine + 1 ?>">Semaine suivante →</a>
    </div>

    <!-- Tableau représentant l'agenda sous forme d'une grille jour/heure -->
    <table class="calendar">
        <thead>
            <tr>
                <th class="heure-col"></th> <!-- Colonne vide pour les heures -->
                <?php for ($i = 0; $i < 5; $i++):
                    $jour = (clone $ajd)->modify("+$i days"); ?>
                    <!-- En-têtes des jours avec jour de la semaine et date -->
                    <th class="jour-col"><?= $jours[$i] ?><br><?= $jour->format('d/m') ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <!-- Colonne des heures (de 8h à 20h) -->
                <td class="heure-col">
                    <?php for ($h = 8; $h <= 20; $h++): ?>
                        <div style="height:60px; line-height:60px;"><?= $h ?>:00</div>
                    <?php endfor; ?>
                </td>

                <!-- Colonnes des jours avec événements placés en position absolue selon heure -->
                <?php for ($i = 0; $i < 5; $i++):
                    $jour = (clone $ajd)->modify("+$i days");
                    $d = $jour->format('Y-m-d'); ?>
                    <td class="jour-col" style="position:relative; height:780px;">

                        <?php
                        $evenementsAffiches = [];

                        // Ajout des événements depuis la base de données
                        if (isset($evenementsBase[$d])) {
                            foreach ($evenementsBase[$d] as $ev) {
                                if (heureToMinutes($ev['heurefin']) > heureToMinutes($ev['heuredebut'])) {
                                    $evenementsAffiches[] = $ev;
                                }
                            }
                        }

                        // Ajout des événements créés en session (non enregistrés en base)
                        if (isset($_SESSION['evenements'][$conseillerSelectionne][$d])) {
                            foreach ($_SESSION['evenements'][$conseillerSelectionne][$d] as $ev) {
                                if (heureToMinutes($ev['heurefin']) > heureToMinutes($ev['heuredebut'])) {
                                    $evenementsAffiches[] = [
                                        'source' => 'session',
                                        'conseiller' => $conseillerSelectionne,
                                        'date' => $d,
                                        'heuredebut' => $ev['heuredebut'],
                                        'heurefin' => $ev['heurefin'],
                                        'objet' => $ev['texte'],
                                        'type' => '',
                                        'typage' => '',
                                        'absence' => 0,
                                        'lieu' => '',
                                        'present' => null,
                                        'motif_absence' => null,
                                        'site' => null,
                                    ];
                                }
                            }
                        }

                        // Tri des événements par heure de début
                        usort($evenementsAffiches, fn($a, $b) => heureToMinutes($a['heuredebut']) <=> heureToMinutes($b['heuredebut']));

                        // Affichage des événements dans la cellule du jour
                        foreach ($evenementsAffiches as $index => $ev):
                            $debut = $ev['heuredebut'] ?? '08:00';
                            $fin = $ev['heurefin'] ?? '09:00';
                            $top = (heureToMinutes($debut) - 480); // Position verticale en px (8h = 480min)
                            $height = max(heureToMinutes($fin) - heureToMinutes($debut), 30); // hauteur min 30px

                            $classAbsent = ($ev['absence'] == 1) ? ' absent' : '';
                            $classSource = 'evenement-' . htmlspecialchars($ev['source']);
                            $typeAffiche = trim($ev['type'] ?? '');

                            $texteCase = htmlspecialchars($debut . ' - ' . $fin . ' : ' . ($typeAffiche ? "[$typeAffiche] " : '') . ($ev['objet'] ?? ''));

                            ?>
                            <div
                                class="event<?= $classAbsent ?> <?= $classSource ?>"
                                style="position:absolute; top:<?= $top ?>px; height:<?= $height ?>px; left:5px; right:5px; cursor:pointer;"
                                data-index="<?= $index ?>"
                                data-date="<?= $d ?>"
                                data-heuredebut="<?= htmlspecialchars($debut) ?>"
                                data-heurefin="<?= htmlspecialchars($fin) ?>"
                                data-objet="<?= htmlspecialchars($ev['objet'] ?? '') ?>"
                                data-type="<?= htmlspecialchars($ev['type'] ?? '') ?>"
                                data-typage="<?= htmlspecialchars($ev['typage'] ?? '') ?>"
                                data-lieu="<?= htmlspecialchars($ev['lieu'] ?? '') ?>"
                                data-absence="<?= htmlspecialchars($ev['absence'] ?? 0) ?>"
                            ><?= $texteCase ?></div>
                        <?php endforeach; ?>

                    </td>
                <?php endfor; ?>
            </tr>
        </tbody>
    </table>

    <hr />

    <!-- Formulaire pour ajouter un nouvel événement -->
    <form method="POST" style="margin-top: 1em;">
        <input type="hidden" name="conseiller" value="<?= htmlspecialchars($conseillerSelectionne) ?>" />
        <label for="date">Date :</label>
        <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required />

        <label for="heuredebut">Heure début :</label>
        <input type="time" id="heuredebut" name="heuredebut" value="08:00" required />

        <label for="heurefin">Heure fin :</label>
        <input type="time" id="heurefin" name="heurefin" value="09:00" required />

        <label for="evenement">Événement :</label>
        <input type="text" id="evenement" name="evenement" required />

        <button type="submit">Ajouter événement</button>
    </form>

</div>

<!-- Popup affichant les détails d'un événement au clic -->
<div id="popup" style="display:none; position:fixed; top:50px; left:50%; transform:translateX(-50%); background:#fff; border:1px solid #ccc; padding:1em; max-width:400px; box-shadow:0 0 10px rgba(0,0,0,0.3); z-index:100;">
    <button id="closePopup" style="float:right;">X</button>
    <h3>Détails de l'événement</h3>
    <p><strong>Date :</strong> <span id="popup-date"></span></p>
    <p><strong>Heure début :</strong> <span id="popup-heuredebut"></span></p>
    <p><strong>Heure fin :</strong> <span id="popup-heurefin"></span></p>
    <p><strong>Objet :</strong> <span id="popup-objet"></span></p>
    <p><strong>Type activité :</strong> <span id="popup-type"></span></p>
    <p><strong>Typage :</strong> <span id="popup-typage"></span></p>
    <p><strong>Lieu :</strong> <span id="popup-lieu"></span></p>
    <p><strong>Absence :</strong> <span id="popup-absence"></span></p>
</div>

<!-- Script JS pour gérer l'ouverture/fermeture du popup d'infos -->
<script>
document.querySelectorAll('.event').forEach(div => {
    div.addEventListener('click', () => {
        document.getElementById('popup-date').textContent = div.getAttribute('data-date');
        document.getElementById('popup-heuredebut').textContent = div.getAttribute('data-heuredebut');
        document.getElementById('popup-heurefin').textContent = div.getAttribute('data-heurefin');
        document.getElementById('popup-objet').textContent = div.getAttribute('data-objet');
        document.getElementById('popup-type').textContent = div.getAttribute('data-type');
        document.getElementById('popup-typage').textContent = div.getAttribute('data-typage');
        document.getElementById('popup-lieu').textContent = div.getAttribute('data-lieu');
        document.getElementById('popup-absence').textContent = (div.getAttribute('data-absence') === '1') ? 'Oui' : 'Non';
        document.getElementById('popup').style.display = 'block';
    });
});

document.getElementById('closePopup').addEventListener('click', () => {
    document.getElementById('popup').style.display = 'none';
});
</script>
</body>
</html>
