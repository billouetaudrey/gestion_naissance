<?php
include 'config.php';

// --- Traitement du formulaire d'ajout de naissance ---
if (isset($_POST['action']) && $_POST['action'] == 'add_naissance') {
    $annee = isset($_POST['annee_hidden']) && !empty($_POST['annee_hidden']) ? $_POST['annee_hidden'] : $_POST['annee'];
    $nombre_naissances = $_POST['nombre_naissances'];
    // L'entrée 'couple' est supprimée car elle n'est plus utile dans le formulaire
    $morph = $_POST['morph'];

    // La requête SQL est mise à jour pour ne plus inclure la colonne 'couple'
    $stmt = $conn->prepare("INSERT INTO naissances (annee, nombre_naissances, morph) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $annee, $nombre_naissances, $morph);

    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// --- Traitement du formulaire d'ajout de vente ---
if (isset($_POST['action']) && $_POST['action'] == 'add_vente') {
    $naissance_id = $_POST['naissance_id'];
    $nombre_serpents = $_POST['nombre_serpents'];
    $prix_unitaire = $_POST['prix_unitaire'];
    $date_vente = $_POST['date_vente'];

    $prix_total = $nombre_serpents * $prix_unitaire;

    $stmt = $conn->prepare("INSERT INTO ventes (naissance_id, nombre_serpents, prix_unitaire, prix_total, date_vente) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iidds", $naissance_id, $nombre_serpents, $prix_unitaire, $prix_total, $date_vente);

    if ($stmt->execute()) {
        $update_stmt = $conn->prepare("UPDATE naissances SET nombre_vendus = nombre_vendus + ? WHERE id = ?");
        $update_stmt->bind_param("ii", $nombre_serpents, $naissance_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// --- Traitement de la suppression de naissance ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_naissance') {
    $id_to_delete = $_POST['id'];

    $stmt = $conn->prepare("DELETE FROM naissances WHERE id = ?");
    $stmt->bind_param("i", $id_to_delete);
    
    if ($stmt->execute()) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
}

// --- Traitement de la suppression de vente ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_vente') {
    $id_to_delete = $_POST['id'];

    $stmt_select = $conn->prepare("SELECT nombre_serpents, naissance_id FROM ventes WHERE id = ?");
    $stmt_select->bind_param("i", $id_to_delete);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();
    $row_select = $result_select->fetch_assoc();
    $nombre_serpents_vendus = $row_select['nombre_serpents'];
    $naissance_id = $row_select['naissance_id'];
    $stmt_select->close();

    $stmt_delete = $conn->prepare("DELETE FROM ventes WHERE id = ?");
    $stmt_delete->bind_param("i", $id_to_delete);

    if ($stmt_delete->execute()) {
        $update_stmt = $conn->prepare("UPDATE naissances SET nombre_vendus = nombre_vendus - ? WHERE id = ?");
        $update_stmt->bind_param("ii", $nombre_serpents_vendus, $naissance_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error: " . $stmt_delete->error;
    }
    $stmt_delete->close();
}

// --- Détermination de l'année sélectionnée ---
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : null;

// --- Récupération des années disponibles pour le filtre ---
$sql_years = "SELECT DISTINCT annee FROM naissances ORDER BY annee DESC";
$result_years = $conn->query($sql_years);

// --- Récupération des données pour l'affichage (avec filtre) ---
// La colonne 'couple' est retirée de la sélection
$sql_naissances = "SELECT id, annee, nombre_naissances, nombre_vendus, morph FROM naissances";
if ($selected_year) {
    $sql_naissances .= " WHERE annee = " . $selected_year;
}
$sql_naissances .= " ORDER BY annee DESC";
$result_naissances = $conn->query($sql_naissances);

// La colonne 'couple' est remplacée par 'morph' dans la jointure
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
    <script>
        function confirmDelete(message) {
            return confirm(message);
        }
    </script>
</head>
<body>

    <div class="container">
        <h1>Gestion des serpents</h1>
        
        <div class="navigation">
            <a href="finances.php" class="cta-button">Voir les finances</a>
        </div>

        <hr>

        <div class="section total-section">
            <h2>Serpents disponibles à la vente : <span class="highlight"><?php echo $total_disponible; ?></span></h2>
        </div>
        
        <hr>

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

                <div id="annee-group">
                    <label for="annee">Année :</label>
                    <input type="number" id="annee" name="annee" required>
                </div>
                
                <label for="nombre_naissances">Nombre de naissances :</label>
                <input type="number" id="nombre_naissances" name="nombre_naissances" required>

                <label for="morph">Morphologie :</label>
                <input type="text" id="morph" name="morph" required>

                <button type="submit">Ajouter naissance</button>
            </form>
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
                                <td><?php echo $row["nombre_vendus"]; ?></td>
                                <td><?php echo $row["nombre_naissances"] - $row["nombre_vendus"]; ?></td>
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirmDelete('Êtes-vous sûr de vouloir supprimer cette naissance et toutes les ventes associées ?');">
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
                <p>Aucune donnée de naissance enregistrée<?php echo $selected_year ? ' pour l\'année ' . $selected_year : ''; ?>.</p>
            <?php endif; ?>
        </div>

        <hr>
        
        <div class="section">
            <h2>Ajouter une vente</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="action" value="add_vente">
                <label for="naissance_id">Naissance liée :</label>
                <select id="naissance_id" name="naissance_id" required>
                    <?php 
                    $conn_select = new mysqli($servername, $username, $password, $dbname);
                    $sql_naissances_select = "SELECT id, annee, morph FROM naissances";
                    if ($selected_year) {
                        $sql_naissances_select .= " WHERE annee = " . $selected_year;
                    }
                    $sql_naissances_select .= " ORDER BY annee DESC, morph ASC";
                    $result_select = $conn_select->query($sql_naissances_select);
                    if ($result_select->num_rows > 0) {
                        while($row_select = $result_select->fetch_assoc()) {
                            echo '<option value="' . $row_select["id"] . '">' . $row_select["annee"] . ' - ' . htmlspecialchars($row_select["morph"]) . '</option>';
                        }
                    } else {
                         echo '<option value="" disabled>Aucune naissance disponible</option>';
                    }
                    $conn_select->close();
                    ?>
                </select>

                <label for="nombre_serpents">Nombre de serpents :</label>
                <input type="number" id="nombre_serpents" name="nombre_serpents" required>
                
                <label for="prix_unitaire">Prix unitaire (€) :</label>
                <input type="number" step="0.01" id="prix_unitaire" name="prix_unitaire" required>

                <label for="date_vente">Date de vente :</label>
                <input type="date" id="date_vente" name="date_vente" required>
                
                <button type="submit">Enregistrer vente</button>
            </form>
        </div>

        <div class="data-section">
            <h2>Historique des ventes</h2>
            <?php if ($result_ventes->num_rows > 0) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Morphologie</th>
                            <th>Nbr.</th>
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
                                <td><?php echo $row["nombre_serpents"]; ?></td>
                                <td><?php echo number_format($row["prix_unitaire"], 2, ',', '.') . ' €'; ?></td>
                                <td><?php echo number_format($row["prix_total"], 2, ',', '.') . ' €'; ?></td>
                                <td>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirmDelete('Êtes-vous sûr de vouloir supprimer cette vente ?');">
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
                <p>Aucune vente enregistrée<?php echo $selected_year ? ' pour l\'année ' . $selected_year : ''; ?>.</p>
            <?php endif; ?>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const yearSelect = document.getElementById('year-select');
        const addNaissanceSection = document.getElementById('add-naissance-section');
        const anneeInput = document.getElementById('annee');
        const anneeHidden = document.getElementById('annee_hidden');

        // Fonction pour gérer l'affichage du formulaire d'ajout
        function handleFormDisplay() {
            const selectedYear = yearSelect.value;
            if (selectedYear === "") {
                // Si "Toutes les années" est sélectionné
                addNaissanceSection.style.display = 'block'; // Affiche le formulaire
                anneeInput.value = ''; // Efface la valeur de l'input "Année"
                anneeInput.disabled = false; // Active l'input "Année"
                anneeHidden.value = ''; // Efface la valeur de l'input caché
            } else {
                // Si une année spécifique est sélectionnée
                addNaissanceSection.style.display = 'none'; // Masque le formulaire
                anneeHidden.value = selectedYear; // Met à jour l'année dans l'input caché
            }
        }

        // Exécute la fonction au chargement de la page pour le cas où un filtre est déjà actif
        handleFormDisplay();
        
        // Ajoute un écouteur d'événement pour le changement de la liste déroulante
        yearSelect.addEventListener('change', function() {
            handleFormDisplay();
            // Le rechargement de la page se fait déjà via `onchange="this.form.submit()"`
            // donc cette fonction est principalement pour l'expérience utilisateur immédiate.
        });
    });
</script>

</body>
</html>
