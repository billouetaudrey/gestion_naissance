<?php
include 'config.php';

// --- Traitement du formulaire d'ajout de naissance ---
if (isset($_POST['action']) && $_POST['action'] == 'add_naissance') {
    $annee = isset($_POST['annee_hidden']) && !empty($_POST['annee_hidden']) ? $_POST['annee_hidden'] : $_POST['annee'];
    $nombre_naissances = $_POST['nombre_naissances'];
    $morph = $_POST['morph'];
    $males = $_POST['nombre_males'] ?? 0;
    $femelles = $_POST['nombre_femelles'] ?? 0;
    $indet = $_POST['nombre_indet'] ?? 0;

    $conn = new mysqli($servername, $username, $password, $dbname);
    $stmt = $conn->prepare("INSERT INTO naissances (annee, nombre_naissances, morph, nombre_males, nombre_femelles, sexe_indetermine) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiii", $annee, $nombre_naissances, $morph, $males, $femelles, $indet);

    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
}

// --- Traitement du formulaire d'ajout de vente ---
if (isset($_POST['action']) && $_POST['action'] == 'add_vente') {
    $naissance_id = (int)$_POST['naissance_id'];
    $males = (int)$_POST['nombre_males_vendus'];
    $femelles = (int)$_POST['nombre_femelles_vendus'];
    $indet = (int)$_POST['nombre_indet_vendus'];
    $prix_unitaire = (float)$_POST['prix_unitaire'];
    $date_vente = $_POST['date_vente'];

    $total_vendus = $males + $femelles + $indet;
    $prix_total = $total_vendus * $prix_unitaire;

    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // --- NOUVEAU : VERIFICATION DES STOCKS DISPONIBLES PAR SEXE ---
    $stock_stmt = $conn->prepare("SELECT nombre_males, nombre_femelles, sexe_indetermine FROM naissances WHERE id = ?");
    $stock_stmt->bind_param("i", $naissance_id);
    $stock_stmt->execute();
    $result_stock = $stock_stmt->get_result();
    $stock = $result_stock->fetch_assoc();
    $stock_stmt->close();

    $males_dispo = $stock['nombre_males'];
    $femelles_dispo = $stock['nombre_femelles'];
    $indet_dispo = $stock['sexe_indetermine'];
    
    // Si la quantité à vendre est supérieure à la quantité disponible, on affiche une erreur.
    if ($males > $males_dispo || $femelles > $femelles_dispo || $indet > $indet_dispo) {
        echo "<script>alert('Erreur : La quantité de serpents vendus dépasse le nombre disponible par sexe.'); window.location.href='index.php';</script>";
    } else {
        // L'ancienne logique de vente est déplacée ici.
        $stmt = $conn->prepare("INSERT INTO ventes (naissance_id, nombre_serpents, prix_unitaire, prix_total, date_vente, nombre_males, nombre_femelles, nombre_indet) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiddsiii", $naissance_id, $total_vendus, $prix_unitaire, $prix_total, $date_vente, $males, $femelles, $indet);

        if ($stmt->execute()) {
            $update_stmt = $conn->prepare("UPDATE naissances
                SET nombre_vendus = nombre_vendus + ?,
                    nombre_males = nombre_males - ?,
                    nombre_femelles = nombre_femelles - ?,
                    sexe_indetermine = sexe_indetermine - ?
                WHERE id = ?");
            $update_stmt->bind_param("iiiii", $total_vendus, $males, $femelles, $indet, $naissance_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    $conn->close();
}

// --- Traitement de la suppression de naissance ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_naissance') {
    $id_to_delete = $_POST['id'];

    $conn = new mysqli($servername, $username, $password, $dbname);
    $stmt = $conn->prepare("DELETE FROM naissances WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
    $conn->close();
}

// --- Traitement de la suppression de vente ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_vente') {
    $id_to_delete = $_POST['id'];

    $conn = new mysqli($servername, $username, $password, $dbname);
    $stmt_select = $conn->prepare("SELECT nombre_serpents, naissance_id, nombre_males, nombre_femelles, nombre_indet FROM ventes WHERE id = ?");
    $stmt_select->bind_param("i", $id_to_delete);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();
    $row_select = $result_select->fetch_assoc();
    $nombre_serpents_vendus = $row_select['nombre_serpents'];
    $naissance_id = $row_select['naissance_id'];
    $males = $row_select['nombre_males'];
    $femelles = $row_select['nombre_femelles'];
    $indet = $row_select['nombre_indet'];
    $stmt_select->close();

    $stmt_delete = $conn->prepare("DELETE FROM ventes WHERE id = ?");
    $stmt_delete->bind_param("i", $id_to_delete);

    if ($stmt_delete->execute()) {
        $update_stmt = $conn->prepare("UPDATE naissances
            SET nombre_vendus = nombre_vendus - ?,
                nombre_males = nombre_males + ?,
                nombre_femelles = nombre_femelles + ?,
                sexe_indetermine = sexe_indetermine + ?
            WHERE id = ?");
        $update_stmt->bind_param("iiiii", $nombre_serpents_vendus, $males, $femelles, $indet, $naissance_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt_delete->error;
    }
    $stmt_delete->close();
    $conn->close();
}

// --- Détermination de l'année sélectionnée ---
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : null;

// --- Connexion à la base de données pour l'affichage ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- Récupération des années disponibles pour le filtre ---
$sql_years = "SELECT DISTINCT annee FROM naissances ORDER BY annee DESC";
$result_years = $conn->query($sql_years);

// --- Récupération des données pour l'affichage (avec filtre) ---
$sql_naissances = "SELECT id, annee, nombre_naissances, nombre_vendus, morph, nombre_males, nombre_femelles, sexe_indetermine FROM naissances";
if ($selected_year) {
    $sql_naissances .= " WHERE annee = " . $selected_year;
}
$sql_naissances .= " ORDER BY annee DESC";
$result_naissances = $conn->query($sql_naissances);

$sql_ventes = "SELECT v.*, n.annee, n.morph FROM ventes v JOIN naissances n ON v.naissance_id = n.id";
if ($selected_year) {
    $sql_ventes .= " WHERE n.annee = " . $selected_year;
}
$sql_ventes .= " ORDER BY v.date_vente DESC";
$result_ventes = $conn->query($sql_ventes);

// --- Calcul du total des serpents disponibles ---
$sql_total = "SELECT SUM(nombre_naissances - nombre_vendus) AS total_disponible FROM naissances";
$result_total = $conn->query($sql_total);
$total_disponible = 0;
if ($result_total->num_rows > 0) {
    $row_total = $result_total->fetch_assoc();
    $total_disponible = $row_total['total_disponible'];
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des naissances et ventes de serpents</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cta-button, .form-button {
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background-color: #007bff;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }
        .sold-btn { background-color: #28a745; }
        .sold-btn:hover { background-color: #218838; }
        .delete-btn { background-color: #dc3545; }
        .delete-btn:hover { background-color: #c82333; }
        .modal { display:none; position:fixed; z-index:1; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.4); padding-top:60px; }
        .modal-content { background:#333; margin:5% auto; padding:20px; border:1px solid #888; width:80%; max-width:500px; border-radius:8px; color:white; }
        .close { color:#aaa; float:right; font-size:28px; font-weight:bold; }
        .close:hover { color:#fff; cursor:pointer; }
    </style>
</head>
<body>

<div class="container">
    <h1>Gestion des serpents</h1>

    <div class="section total-section">
        <h2>Serpents disponibles à la vente : <span class="highlight"><?php echo $total_disponible; ?></span></h2>
    </div>

    <div class="section filter-section">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="filter-form">
            <label for="year-select">Filtrer par année :</label>
            <select id="year-select" name="year" onchange="this.form.submit()">
                <option value="">Toutes les années</option>
                <?php 
                if ($result_years->num_rows > 0) {
                    while($row_year = $result_years->fetch_assoc()) {
                        $year = $row_year['annee'];
                        $selected = ($year == $selected_year) ? 'selected' : '';
                        echo '<option value="' . $year . '" ' . $selected . '>' . $year . '</option>';
                    }
                }
                ?>
            </select>
        </form>
    </div>

    <div class="section" id="add-naissance-section" style="<?php echo $selected_year ? 'display: none;' : ''; ?>">
        <h2>Ajouter une naissance</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="action" value="add_naissance">
            <input type="hidden" id="annee_hidden" name="annee_hidden" value="<?php echo $selected_year; ?>">

            <label for="annee">Année :</label>
            <input type="number" id="annee" name="annee" required>

            <label for="nombre_naissances">Nombre total :</label>
            <input type="number" id="nombre_naissances" name="nombre_naissances" required>

            <label for="nombre_males">Mâles :</label>
            <input type="number" id="nombre_males" name="nombre_males" value="0">

            <label for="nombre_femelles">Femelles :</label>
            <input type="number" id="nombre_femelles" name="nombre_femelles" value="0">

            <label for="nombre_indet">Indéterminés :</label>
            <input type="number" id="nombre_indet" name="nombre_indet" value="0">

            <label for="morph">Morphologie :</label>
            <input type="text" id="morph" name="morph" required>

            <button type="submit" class="form-button">Ajouter naissance</button>
        </form>
    </div>


    <div class="container">
        <h1>Gestion des serpents</h1>

        <div class="navigation">
            <a href="finances.php" class="cta-button">Voir les finances</a>
        </div>

        <hr>

        <div class="section total-section">
            <h2>Serpents disponibles à la vente : <span class="highlight"><?php echo $total_disponible; ?></span></h2>
        </div>


    <div class="data-section">
        <h2>Récapitulatif des naissances</h2>
        <?php if ($result_naissances->num_rows > 0) : ?>
            <table>
                <thead>
                    <tr>
                        <th>Année</th>
                        <th>Morphologie</th>
                        <th>Nés</th>
                        <th>Mâles</th>
                        <th>Femelles</th>
                        <th>Indet.</th>
                        <th>Vend.</th>
                        <th>Dispo.</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result_naissances->fetch_assoc()) : ?>
                        <tr>
                            <td><?php echo $row["annee"]; ?></td>
                            <td><?php echo htmlspecialchars($row["morph"]); ?></td>
                            <td><?php echo $row["nombre_naissances"]; ?></td>
                            <td><?php echo $row["nombre_males"]; ?></td>
                            <td><?php echo $row["nombre_femelles"]; ?></td>
                            <td><?php echo $row["sexe_indetermine"]; ?></td>
                            <td><?php echo $row["nombre_vendus"]; ?></td>
                            <td><?php echo $row["nombre_naissances"] - $row["nombre_vendus"]; ?></td>
                            <td>
                                <button class="sold-btn" onclick="openSoldModal(<?php echo $row['id']; ?>, <?php echo $row['nombre_males']; ?>, <?php echo $row['nombre_femelles']; ?>, <?php echo $row['sexe_indetermine']; ?>)">Vendu</button>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Supprimer cette naissance ?');" style="display:inline-block;">
                                    <input type="hidden" name="action" value="delete_naissance">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="delete-btn">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>Aucune donnée</p>
        <?php endif; ?>
    </div>

    <div class="data-section">
        <h2>Historique des ventes</h2>
        <?php if ($result_ventes->num_rows > 0) : ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Morphologie</th>
                        <th>Mâles</th>
                        <th>Femelles</th>
                        <th>Indet.</th>
                        <th>Total</th>
                        <th>Prix Unit.</th>
                        <th>Prix Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result_ventes->fetch_assoc()) : ?>
                        <tr>
                            <td><?php echo date("d/m/Y", strtotime($row["date_vente"])); ?></td>
                            <td><?php echo htmlspecialchars($row["morph"]); ?></td>
                            <td><?php echo $row["nombre_males"]; ?></td>
                            <td><?php echo $row["nombre_femelles"]; ?></td>
                            <td><?php echo $row["nombre_indet"]; ?></td>
                            <td><?php echo $row["nombre_serpents"]; ?></td>
                            <td><?php echo number_format($row["prix_unitaire"], 2, ',', '.') . ' €'; ?></td>
                            <td><?php echo number_format($row["prix_total"], 2, ',', '.') . ' €'; ?></td>
                            <td>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Supprimer cette vente ?');">
                                    <input type="hidden" name="action" value="delete_vente">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="delete-btn">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>Aucune vente enregistrée</p>
        <?php endif; ?>
    </div>
</div>

<div id="soldModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeSoldModal()">&times;</span>
        <h2>Enregistrer une vente</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="action" value="add_vente">
            <input type="hidden" id="naissance_id_sold" name="naissance_id">

            <label>Mâles vendus (<span id="males_stock">0</span>) :</label>
            <input type="number" name="nombre_males_vendus" value="0" min="0">

            <label>Femelles vendues (<span id="femelles_stock">0</span>) :</label>
            <input type="number" name="nombre_femelles_vendus" value="0" min="0">

            <label>Indéterminés vendus (<span id="indet_stock">0</span>) :</label>
            <input type="number" name="nombre_indet_vendus" value="0" min="0">

            <label>Prix unitaire (€) :</label>
            <input type="number" name="prix_unitaire" step="0.01" required>

            <label>Date de vente :</label>
            <input type="date" name="date_vente" required>

            <button type="submit" class="form-button">Enregistrer</button>
        </form>
    </div>
</div>

<script>
// La fonction `openSoldModal` accepte maintenant des paramètres pour les stocks
function openSoldModal(naissanceId, malesStock, femellesStock, indetStock) {
    // Remplissage de l'ID de la naissance
    document.getElementById('naissance_id_sold').value = naissanceId;

    // Affichage des stocks disponibles à côté des libellés
    document.getElementById('males_stock').textContent = malesStock;
    document.getElementById('femelles_stock').textContent = femellesStock;
    document.getElementById('indet_stock').textContent = indetStock;

    // Réinitialisation des champs de saisie à 0
    document.querySelector('input[name="nombre_males_vendus"]').value = 0;
    document.querySelector('input[name="nombre_femelles_vendus"]').value = 0;
    document.querySelector('input[name="nombre_indet_vendus"]').value = 0;
    
    // Affichage de la modale
    document.getElementById('soldModal').style.display = "block";
}
function closeSoldModal() {
    document.getElementById('soldModal').style.display = "none";
}
</script>

</body>
</html>
