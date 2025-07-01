<?php 
session_start();
$_SESSION['evenements'] = $_SESSION['evenements'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? null;
    $evenement = trim($_POST['evenement'] ?? '');
    $delete = $_POST['delete'] ?? null;

    if ($date && $evenement && $delete === null) {
        $_SESSION['evenements'][$date][] = $evenement;
    }

    if ($date && $delete !== null && isset($_SESSION['evenements'][$date][$delete])) {
        unset($_SESSION['evenements'][$date][$delete]);

        if (!empty($_SESSION['evenements'][$date])) {
            $_SESSION['evenements'][$date] = array_values($_SESSION['evenements'][$date]);
        } else {
            unset($_SESSION['evenements'][$date]);
        }
    }
}

$semaine = (int)($_GET['semaine'] ?? 0);
$ajd = new DateTime();
$ajd->modify('-' . ($ajd->format('N') - 1) . ' days')->modify("{$semaine} weeks");
$jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Agenda</title>
  <link rel="stylesheet" href="calendrier.css">
</head>
<body>
<div class="calendar-container">
  <h2>Semaine du <?= $ajd->format('d/m/Y') ?></h2>
  <div class="navigation">
    <a href="?semaine=<?= $semaine - 1 ?>">← Précédente</a>
    <a href="?semaine=0">Aujourd'hui</a>
    <a href="?semaine=<?= $semaine + 1 ?>">Suivante →</a>
  </div>

  <table class="calendar" border="1">
    <tr>
      <?php for ($i = 0; $i < 5; $i++): 
        $jour = (clone $ajd)->modify("+$i days"); ?>
        <th><div><?= $jours[$i] ?><br><?= $jour->format('d/m') ?></div></th>
      <?php endfor; ?>
    </tr>
    <tr>
      <?php for ($i = 0; $i < 5; $i++): 
        $jour = (clone $ajd)->modify("+$i days");
        $d = $jour->format('Y-m-d'); ?>
        <td>
          <?php foreach ($_SESSION['evenements'][$d] ?? [] as $k => $ev): ?>
            <div class='evenement'>
              <?= htmlspecialchars($ev) ?>
              <form method='POST' style='display:inline'>
                <input type='hidden' name='date' value='<?= $d ?>'>
                <button type='submit' name='delete' value='<?= $k ?>'>X</button>
              </form>
            </div>
          <?php endforeach; ?>
          <form method='POST' class='form-evenement'>
            <input type='hidden' name='date' value='<?= $d ?>'>
            <input type='text' name='evenement' placeholder='Ajouter...' required>
            <button type='submit'>OK</button>
          </form>
        </td>
      <?php endfor; ?>
    </tr>
  </table>
</div>

<?php if (isset($_GET['import'])): ?>
  <p style="color:<?= $_GET['import'] === 'ok' ? 'green' : 'red' ?>">
    <?= $_GET['import'] === 'ok' ? '✅ Importation réussie' : '❌ Erreur d’importation' ?>
  </p>
<?php endif; ?>
</body>
</html>
