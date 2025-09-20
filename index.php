<?php
include 'config.php';

// --- Traitement du formulaire d'ajout de naissance ---
if (isset($_POST['action']) && $_POST['action'] == 'add_naissance') {
    $annee = isset($_POST['annee_hidden']) && !empty($_POST['annee_hidden']) ? $_POST['annee_hidden'] : $_POST['annee'];
    $nombre_naissances = $_POST['nombre_naissances'];
    $morph = $_POST['morph'];

    $conn = new mysqli($servername, $username, $password, $dbname);
    $stmt = $conn->prepare("INSERT INTO naissances (annee, nombre_naissances, morph) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $annee, $nombre_naissances, $morph);

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
    $naissance_id = $_POST['naissance_id'];
    $nombre_serpents = $_POST['nombre_serpents'];
    $prix_unitaire = $_POST['prix_unitaire'];
    $date_vente = $_POST['date_vente'];

    $prix_total = $nombre_serpents * $prix_unitaire;

    $conn = new mysqli($servername, $username, $password, $dbname);
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
    <style>
        .cta-button, .form-button {
            display: inline-block;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background-color: #007bff; /* Un bleu classique */
            border: none;
            border-radius: 5px;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
        }

        .cta-button:hover, .form-button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        }

        .cta-button:active, .form-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .sold-btn {
            background-color: #28a745; /* Vert pour le bouton "Vendu" */
        }

        .sold-btn:hover {
            background-color: #218838;
        }

        .delete-btn {
            background-color: #dc3545; /* Rouge pour le bouton "Supprimer" */
        }

        .delete-btn:hover {
            background-color: #c82333;
        }

        /* Styles de la modale */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #333;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
            color: white;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }
        .close:hover,
        .close:focus {
            color: #fff;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
    <script>
        function confirmDelete(message) {
            return confirm(message);
        }

        function openSoldModal(naissanceId) {
            document.getElementById('naissance_id_sold').value = naissanceId;
            document.getElementById('soldModal').style.display = "block";
        }

        function closeSoldModal() {
            document.getElementById('soldModal').style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('soldModal')) {
                closeSoldModal();
            }
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

                <button type="submit" class="form-button">Ajouter naissance</button>
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
                                <td class="action-buttons">
                                    <button class="sold-btn" onclick="openSoldModal(<?php echo $row['id']; ?>)">Vendu</button>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirmDelete('Êtes-vous sûr de vouloir supprimer cette naissance et toutes les ventes associées ?');" style="display:inline-block;">
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

    <div id="soldModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSoldModal()">&times;</span>
            <h2>Enregistrer une vente</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="action" value="add_vente">
                <input type="hidden" id="naissance_id_sold" name="naissance_id">

                <label for="nombre_serpents_sold">Nombre de serpents :</label>
                <input type="number" id="nombre_serpents_sold" name="nombre_serpents" required>

                <label for="prix_unitaire_sold">Prix unitaire (€) :</label>
                <input type="number" step="0.01" id="prix_unitaire_sold" name="prix_unitaire" required>

                <label for="date_vente_sold">Date de vente :</label>
                <input type="date" id="date_vente_sold" name="date_vente" required>

                <button type="submit" class="form-button sold-btn">Enregistrer</button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const yearSelect = document.getElementById('year-select');
            const addNaissanceSection = document.getElementById('add-naissance-section');
            const anneeInput = document.getElementById('annee');
            const anneeHidden = document.getElementById('annee_hidden');

            function handleFormDisplay() {
                const selectedYear = yearSelect.value;
                if (selectedYear === "") {
                    addNaissanceSection.style.display = 'block';
                    anneeInput.value = '';
                    anneeInput.disabled = false;
                    anneeHidden.value = '';
                } else {
                    addNaissanceSection.style.display = 'none';
                    anneeHidden.value = selectedYear;
                }
            }

            handleFormDisplay();
            yearSelect.addEventListener('change', function() {
                handleFormDisplay();
            });
        });

        function confirmDelete(message) {
            return confirm(message);
        }

        function openSoldModal(naissanceId) {
            document.getElementById('naissance_id_sold').value = naissanceId;
            document.getElementById('soldModal').style.display = "block";
            // Pré-remplir la date d'aujourd'hui
            document.getElementById('date_vente_sold').valueAsDate = new Date();
        }

        function closeSoldModal() {
            document.getElementById('soldModal').style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('soldModal')) {
                closeSoldModal();
            }
        }
    </script>
</body>
</html>
