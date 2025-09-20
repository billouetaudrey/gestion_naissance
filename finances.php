<?php
include 'config.php';

// --- Traitement du formulaire d'ajout de dépense ---
if (isset($_POST['action']) && $_POST['action'] == 'add_depense') {
    $date_depense = $_POST['date_depense'];
    $description = $_POST['description'];
    $montant = $_POST['montant'];
    $annee = date('Y', strtotime($date_depense));

    $stmt = $conn->prepare("INSERT INTO depenses (date_depense, description, montant, annee) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $date_depense, $description, $montant, $annee);

    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// --- Traitement de la suppression de dépense ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_depense') {
    $id_to_delete = $_POST['id'];

    $stmt = $conn->prepare("DELETE FROM depenses WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// --- Détermination de l'année sélectionnée ---
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : null;

// --- Récupération des années disponibles pour le filtre ---
$sql_years_naissances = "SELECT DISTINCT annee FROM naissances";
$sql_years_depenses = "SELECT DISTINCT annee FROM depenses";
$result_years_naissances = $conn->query($sql_years_naissances);
$result_years_depenses = $conn->query($sql_years_depenses);

$years = [];
while($row = $result_years_naissances->fetch_assoc()) {
    $years[] = $row['annee'];
}
while($row = $result_years_depenses->fetch_assoc()) {
    $years[] = $row['annee'];
}
$years = array_unique($years);
rsort($years);

// --- Calcul des totaux des ventes et des dépenses pour l'année sélectionnée ---
$sql_total_ventes = "SELECT SUM(prix_total) AS total_revenu FROM ventes v JOIN naissances n ON v.naissance_id = n.id";
if ($selected_year) {
    $sql_total_ventes .= " WHERE n.annee = " . $selected_year;
}
$result_total_ventes = $conn->query($sql_total_ventes);
$total_revenu = $result_total_ventes->fetch_assoc()['total_revenu'] ?? 0;

$sql_total_depenses = "SELECT SUM(montant) AS total_depense FROM depenses";
if ($selected_year) {
    $sql_total_depenses .= " WHERE annee = " . $selected_year;
}
$result_total_depenses = $conn->query($sql_total_depenses);
$total_depense = $result_total_depenses->fetch_assoc()['total_depense'] ?? 0;

$benefice = $total_revenu - $total_depense;

// --- Récupération des données pour l'affichage ---
// Ligne 81 : n.couple a été remplacé par n.morph
$sql_ventes = "SELECT v.*, n.annee, n.morph FROM ventes v JOIN naissances n ON v.naissance_id = n.id";
if ($selected_year) {
    $sql_ventes .= " WHERE n.annee = " . $selected_year;
}
$sql_ventes .= " ORDER BY v.date_vente DESC";
$result_ventes = $conn->query($sql_ventes);

$sql_depenses = "SELECT * FROM depenses";
if ($selected_year) {
    $sql_depenses .= " WHERE annee = " . $selected_year;
}
$sql_depenses .= " ORDER BY date_depense DESC";
$result_depenses = $conn->query($sql_depenses);

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des finances</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function confirmDelete(message) {
            return confirm(message);
        }
    </script>
</head>
<body>

    <div class="container">
        <h1>Gestion des finances</h1>
        
        <div class="navigation">
            <a href="index.php" class="cta-button">Retour à la gestion des serpents</a>
        </div>
        
        <hr>

        <div class="section filter-section">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
                <label for="year-select">Filtrer par année :</label>
                <select id="year-select" name="year" onchange="this.form.submit()">
                    <option value="">Toutes les années</option>
                    <?php 
                    foreach ($years as $year) {
                        $selected = ($year == $selected_year) ? 'selected' : '';
                        echo '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
                    }
                    ?>
                </select>
            </form>
        </div>

        <div class="data-section">
            <h2>Récapitulatif financier</h2>
            <div class="summary">
                <p><strong>Revenu total des ventes :</strong> <span class="income"><?php echo number_format($total_revenu, 2, ',', '.') . ' €'; ?></span></p>
                <p><strong>Total des dépenses :</strong> <span class="expense"><?php echo number_format($total_depense, 2, ',', '.') . ' €'; ?></span></p>
                <p><strong>Bénéfice net :</strong> <span class="<?php echo ($benefice >= 0) ? 'positive' : 'negative'; ?>"><?php echo number_format($benefice, 2, ',', '.') . ' €'; ?></span></p>
            </div>
        </div>

        <hr>

        <div class="section">
            <h2>Ajouter une dépense</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="action" value="add_depense">
                <label for="date_depense">Date de la dépense :</label>
                <input type="date" id="date_depense" name="date_depense" required>
                <label for="description">Description :</label>
                <input type="text" id="description" name="description" required>
                <label for="montant">Montant (€) :</label>
                <input type="number" step="0.01" id="montant" name="montant" required>
                <button type="submit">Enregistrer la dépense</button>
            </form>
        </div>

        <div class="data-section">
            <h2>Détail des dépenses</h2>
            <?php if ($result_depenses->num_rows > 0) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Montant</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result_depenses->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo date("d/m/Y", strtotime($row["date_depense"])); ?></td>
                                <td><?php echo htmlspecialchars($row["description"]); ?></td>
                                <td><?php echo number_format($row["montant"], 2, ',', '.') . ' €'; ?></td>
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirmDelete('Êtes-vous sûr de vouloir supprimer cette dépense ?');">
                                        <input type="hidden" name="action" value="delete_depense">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="delete-btn">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>Aucune dépense enregistrée<?php echo $selected_year ? ' pour l\'année ' . $selected_year : ''; ?>.</p>
            <?php endif; ?>
        </div>
        
        <hr>

        <div class="data-section">
            <h2>Détail des ventes</h2>
            <?php if ($result_ventes->num_rows > 0) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Morphologie</th>
                            <th>Date</th>
                            <th>Nbr. serpents</th>
                            <th>Prix unitaire</th>
                            <th>Prix total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result_ventes->fetch_assoc()) : ?>
                            <tr>
                                <td><?php echo $row["annee"]; ?></td>
                                <td><?php echo htmlspecialchars($row["morph"]); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($row["date_vente"])); ?></td>
                                <td><?php echo $row["nombre_serpents"]; ?></td>
                                <td><?php echo number_format($row["prix_unitaire"], 2, ',', '.') . ' €'; ?></td>
                                <td><?php echo number_format($row["prix_total"], 2, ',', '.') . ' €'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>Aucune vente enregistrée<?php echo $selected_year ? ' pour l\'année ' . $selected_year : ''; ?>.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
