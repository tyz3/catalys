<?php
session_start(); // Démarre la session

// Connexion à la base
$dns = 'mysql:host=localhost;dbname=catalys';
$utilisateur = 'root';
$motDePasse = '';

try {
    $connexion = new PDO($dns, $utilisateur, $motDePasse);
    $connexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "Erreur BDD : ", $e->getMessage();
    die();
}

$erreur = "";

// Si formulaire envoyé
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['idEmploye']) && !empty($_POST['mdpEmploye'])) {
        $idEmploye = $_POST['idEmploye'];
        $mdpEmploye = $_POST['mdpEmploye'];

        // Mot de passe en clair ici, à changer
        $sql = "SELECT * FROM Employes WHERE idEmploye = :idEmploye AND mdpEmploye = :mdpEmploye";
        $stmt = $connexion->prepare($sql);
        $stmt->bindParam(':idEmploye', $idEmploye);
        $stmt->bindParam(':mdpEmploye', $mdpEmploye);

        try {
            $stmt->execute();
        } catch (PDOException $e) {
            echo "Erreur requête : " . $e->getMessage();
            die();
        }

        if ($stmt->rowCount() > 0) {
            $_SESSION['idEmploye'] = $idEmploye;
            header("Location: calendrier.php");
            exit();
        } else {
            $erreur = "Identifiant ou mot de passe incorrect.";
        }
    } else {
        $erreur = "Champs manquants.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Se connecter</title>
    <link rel="stylesheet" href="connexion.css">
</head>
<body>
    <div class="form-container">
        <h2>Connectez-vous</h2>

        <?php if (!empty($erreur)): ?>
            <p style="color: red;"><?php echo $erreur; ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <label for="idEmploye">Identifiant :</label>
            <input type="text" id="idEmploye" name="idEmploye" required>

            <label for="mdpEmploye">Mot de passe :</label>
            <input type="password" id="mdpEmploye" name="mdpEmploye" required>

            <button type="submit">
                <img src="images/clé.png" alt="Icône" class="btn-icon">
                Valider
            </button>
        </form>
    </div>
</body>
</html>
