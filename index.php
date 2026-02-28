<?php
session_start();

// --- GESTION DES JOUEURS (AVANT-PARTIE) ---
if (!isset($_SESSION['game_started'])) {
    if (!isset($_SESSION['setup_players'])) $_SESSION['setup_players'] = [];
    
    if (isset($_POST['add_player']) && !empty($_POST['player_name'])) {
        $_SESSION['setup_players'][] = $_POST['player_name'];
        header("Location: index.php"); exit;
    }
    if (isset($_GET['remove_p'])) {
        array_splice($_SESSION['setup_players'], $_GET['remove_p'], 1);
        header("Location: index.php"); exit;
    }
    if (isset($_POST['start_game']) && count($_SESSION['setup_players']) > 0) {
        $_SESSION['players'] = $_SESSION['setup_players'];
        $_SESSION['scores'] = array_fill(0, count($_SESSION['players']), []); // Contiendra des tableaux ['score' => X, 'cards' => [...]]
        $_SESSION['current_p'] = 0;
        $_SESSION['temp_cards'] = [];
        $_SESSION['game_started'] = true;
        header("Location: index.php"); exit;
    }
}

// --- LOGIQUE DE JEU ---
if (isset($_SESSION['game_started'])) {
    $currentPlayer = $_SESSION['current_p'];

    if (isset($_GET['add_card'])) {
        $card = $_GET['add_card'];
        if (is_numeric($card) || $card === 'mult-2') {
            if (!in_array($card, $_SESSION['temp_cards'])) {
                $_SESSION['temp_cards'][] = $card;
            }
        } else {
            $_SESSION['temp_cards'][] = $card;
        }
        header("Location: index.php"); exit;
    }

    if (isset($_GET['del_card_idx'])) {
        array_splice($_SESSION['temp_cards'], $_GET['del_card_idx'], 1);
        header("Location: index.php"); exit;
    }

    if (isset($_GET['action'])) {
        if ($_GET['action'] == 'stop') {
            $sum_digits = 0;
            $sum_specials = 0;
            $digit_count = 0;
            $has_mult = false;

            foreach ($_SESSION['temp_cards'] as $c) {
                if (is_numeric($c)) {
                    $sum_digits += (int)$c;
                    $digit_count++;
                } elseif (str_starts_with($c, 'plus-')) {
                    $sum_specials += (int)filter_var($c, FILTER_SANITIZE_NUMBER_INT);
                } elseif ($c === 'mult-2') {
                    $has_mult = true;
                }
            }

            // Calcul : (Chiffres * Mult) + Cartes Plus + Bonus Flip7
            $finalScore = ($has_mult) ? ($sum_digits * 2) : $sum_digits;
            $finalScore += $sum_specials;
            if ($digit_count >= 7) $finalScore += 15;

            $_SESSION['scores'][$currentPlayer][] = [
                'val' => $finalScore,
                'cards' => $_SESSION['temp_cards']
            ];
            nextTurn();
        } elseif ($_GET['action'] == 'lost') {
            $_SESSION['scores'][$currentPlayer][] = ['val' => 0, 'cards' => ['lost']];
            nextTurn();
        }
        header("Location: index.php"); exit;
    }
}

function nextTurn() {
    $_SESSION['temp_cards'] = [];
    $_SESSION['current_p'] = ($_SESSION['current_p'] + 1) % count($_SESSION['players']);
}

if (isset($_GET['reset'])) { session_destroy(); header("Location: index.php"); exit; }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Flip7 Calculator</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .hist-card { width: 20px; margin: 1px; vertical-align: middle; }
        .score-box { display: flex; flex-direction: column; align-items: center; gap: 5px; }
        .lost-text { color: #dc3545; font-size: 0.8em; font-weight: bold; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['game_started'])): ?>
    <div class="setup">
        <h1>Configuration Flip7</h1>
        <form method="POST">
            <input type="text" name="player_name" placeholder="Nom du joueur" autofocus>
            <button type="submit" name="add_player">Ajouter</button>
        </form>
        <div class="player-list" style="margin: 20px;">
            <?php foreach ($_SESSION['setup_players'] as $idx => $name): ?>
                <div class="avatar"><?= htmlspecialchars($name) ?> <a href="?remove_p=<?= $idx ?>">×</a></div>
            <?php endforeach; ?>
        </div>
        <form method="POST"><button type="submit" name="start_game" class="btn-win">Lancer la partie</button></form>
    </div>

<?php else: ?>
    <header class="deck">
        <?php 
        $all_cards = ['0','1','2','3','4','5','6','7','8','9','10','11','12','plus-2','plus-4','plus-6','plus-8','plus-10','mult-2'];
        foreach ($all_cards as $c): ?>
            <a href="?add_card=<?= $c ?>"><img src="img/cards/<?= $c ?>.png" class="card-btn"></a>
        <?php endforeach; ?>
    </header>

    <main>
        <div class="current-turn">
            <h3>Tour de : <span class="avatar"><?= htmlspecialchars($_SESSION['players'][$currentPlayer]) ?></span></h3>
            <div class="hand">
                <?php $d_count = 0; foreach ($_SESSION['temp_cards'] as $idx => $tc): if(is_numeric($tc)) $d_count++; ?>
                    <a href="?del_card_idx=<?= $idx ?>"><img src="img/cards/<?= $tc ?>.png" class="card-hand"></a>
                <?php endforeach; ?>
            </div>
            <?php if ($d_count >= 7): ?><div class="bonus-gold">BONUS FLIP7 (+15) ACTIVÉ !</div><?php endif; ?>
            <div class="controls">
                <a href="?action=stop" class="btn btn-win">Valider Score</a>
                <a href="?action=lost" class="btn btn-lose">PERDU</a>
            </div>
        </div>

        <table>
            <tr><?php foreach ($_SESSION['players'] as $p): ?><th><div class="avatar"><?= htmlspecialchars($p) ?></div></th><?php endforeach; ?></tr>
            <?php 
            $max_rows = 0;
            foreach ($_SESSION['scores'] as $s) { $max_rows = max($max_rows, count($s)); }
            
            for ($i = 0; $i < $max_rows; $i++): ?>
                <tr>
                    <?php foreach ($_SESSION['scores'] as $p_idx => $p_scores): ?>
                        <td>
                            <?php if (isset($p_scores[$i])): ?>
                                <div class="score-box">
                                    <strong><?= $p_scores[$i]['val'] ?></strong>
                                    <div class="history">
                                        <?php foreach ($p_scores[$i]['cards'] as $hc): ?>
                                            <?php if ($hc === 'lost'): ?>
                                                <span class="lost-text">PERDU</span>
                                            <?php else: ?>
                                                <img src="img/cards/<?= $hc ?>.png" class="hist-card">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
            <tr class="total-row">
                <?php foreach ($_SESSION['scores'] as $p_scores): 
                    $total = array_sum(array_column($p_scores, 'val')); ?>
                    <td><strong><?= $total ?></strong></td>
                <?php endforeach; ?>
            </tr>
        </table>
        <a href="?reset=1" style="color: #666; text-decoration: none;">Réinitialiser la partie</a>
    </main>
<?php endif; ?>
</body>
</html>