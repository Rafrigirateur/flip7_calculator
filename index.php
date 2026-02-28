<?php
session_start();

// --- GESTION DES JOUEURS ET CONFIG (AVANT-PARTIE) ---
if (!isset($_SESSION['game_started'])) {
    if (!isset($_SESSION['setup_players'])) $_SESSION['setup_players'] = [];
    if (!isset($_SESSION['target_score'])) $_SESSION['target_score'] = 200; // Par défaut
    
    if (isset($_POST['add_player']) && !empty($_POST['player_name'])) {
        $_SESSION['setup_players'][] = $_POST['player_name'];
        header("Location: index.php"); exit;
    }
    if (isset($_POST['set_target'])) {
        $_SESSION['target_score'] = (int)$_POST['target_score'];
    }
    if (isset($_GET['remove_p'])) {
        array_splice($_SESSION['setup_players'], $_GET['remove_p'], 1);
        header("Location: index.php"); exit;
    }
    if (isset($_POST['start_game']) && count($_SESSION['setup_players']) > 0) {
        $_SESSION['players'] = $_SESSION['setup_players'];
        $_SESSION['scores'] = array_fill(0, count($_SESSION['players']), []);
        $_SESSION['current_p'] = 0;
        $_SESSION['temp_cards'] = [];
        $_SESSION['game_started'] = true;
        $_SESSION['winner'] = null;
        header("Location: index.php"); exit;
    }
}

// --- LOGIQUE DE JEU ---
if (isset($_SESSION['game_started']) && !isset($_SESSION['winner'])) {
    $currentPlayer = $_SESSION['current_p'];

    if (isset($_GET['add_card'])) {
        $card = $_GET['add_card'];
        if (is_numeric($card) || $card === 'mult-2') {
            if (!in_array($card, $_SESSION['temp_cards'])) $_SESSION['temp_cards'][] = $card;
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
            $sum_digits = 0; $sum_specials = 0; $digit_count = 0; $has_mult = false;
            foreach ($_SESSION['temp_cards'] as $c) {
                if (is_numeric($c)) { $sum_digits += (int)$c; $digit_count++; }
                elseif (str_starts_with($c, 'plus-')) { $sum_specials += (int)filter_var($c, FILTER_SANITIZE_NUMBER_INT); }
                elseif ($c === 'mult-2') { $has_mult = true; }
            }

            $finalScore = ($has_mult) ? ($sum_digits * 2) : $sum_digits;
            $finalScore += $sum_specials;
            $has_bonus = ($digit_count >= 7);
            if ($has_bonus) $finalScore += 15;

            $_SESSION['scores'][$currentPlayer][] = ['val' => $finalScore, 'cards' => $_SESSION['temp_cards'], 'bonus' => $has_bonus];
            
            // Vérification de la victoire par points
            $total = array_sum(array_column($_SESSION['scores'][$currentPlayer], 'val'));
            if ($total >= $_SESSION['target_score']) { checkWinner(); }
            else { nextTurn(); }

        } elseif ($_GET['action'] == 'lost') {
            $_SESSION['scores'][$currentPlayer][] = ['val' => 0, 'cards' => ['lost'], 'bonus' => false];
            nextTurn();
        } elseif ($_GET['action'] == 'end_now') {
            checkWinner();
        }
        header("Location: index.php"); exit;
    }
}

function nextTurn() {
    $_SESSION['temp_cards'] = [];
    $_SESSION['current_p'] = ($_SESSION['current_p'] + 1) % count($_SESSION['players']);
}

function checkWinner() {
    $totals = [];
    foreach ($_SESSION['scores'] as $idx => $p_scores) {
        $totals[$idx] = array_sum(array_column($p_scores, 'val'));
    }
    arsort($totals); // Trie par score décroissant
    $_SESSION['winner'] = $totals;
}

if (isset($_GET['reset'])) { session_destroy(); header("Location: index.php"); exit; }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Flip7 Calculator</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if (!isset($_SESSION['game_started'])): ?>
    <div class="setup">
        <h1>Flip7 Setup</h1>
        <form method="POST">
            <input type="text" name="player_name" placeholder="Nom du joueur" autofocus>
            <button type="submit" name="add_player">Ajouter</button>
        </form>
        <div class="player-list">
            <?php foreach ($_SESSION['setup_players'] as $idx => $name): ?>
                <div class="avatar"><?= htmlspecialchars($name) ?> <a href="?remove_p=<?= $idx ?>">×</a></div>
            <?php endforeach; ?>
        </div>
        <form method="POST" style="margin-top:20px;">
            Objectif : <input type="number" name="target_score" value="<?= $_SESSION['target_score'] ?>" style="width:70px;"> pts
            <button type="submit" name="start_game" class="btn-win">Lancer</button>
        </form>
    </div>

<?php elseif (isset($_SESSION['winner'])): ?>
    <div class="podium">
        <h1>🏆 Résultats Final 🏆</h1>
        <?php $rank = 1; foreach ($_SESSION['winner'] as $idx => $total): ?>
            <div class="rank rank-<?= $rank ?>">
                <span class="medal"><?= ($rank==1)?'🥇':(($rank==2)?'🥈':'🥉') ?></span>
                <strong><?= htmlspecialchars($_SESSION['players'][$idx]) ?></strong> : <?= $total ?> pts
            </div>
        <?php $rank++; endforeach; ?>
        <br><a href="?reset=1" class="btn btn-win">Nouvelle Partie</a>
    </div>

<?php else: ?>
    <header class="deck">
        <?php $all_cards = ['0','1','2','3','4','5','6','7','8','9','10','11','12','plus-2','plus-4','plus-6','plus-8','plus-10','mult-2'];
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
            <?php $max_rows = 0; foreach ($_SESSION['scores'] as $s) { $max_rows = max($max_rows, count($s)); }
            for ($i = 0; $i < $max_rows; $i++): ?>
                <tr>
                    <?php foreach ($_SESSION['scores'] as $p_idx => $p_scores): ?>
                        <td>
                            <?php if (isset($p_scores[$i])): ?>
                                <div class="score-box">
                                    <strong><?= $p_scores[$i]['val'] ?> 
                                        <?php if($p_scores[$i]['bonus']): ?><span class="mini-bonus" title="Bonus Flip7">+15</span><?php endif; ?>
                                    </strong>
                                    <div class="history">
                                        <?php foreach ($p_scores[$i]['cards'] as $hc): ?>
                                            <?php if ($hc === 'lost'): ?><span class="lost-text">PERDU</span>
                                            <?php else: ?><img src="img/cards/<?= $hc ?>.png" class="hist-card"><?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endfor; ?>
            <tr class="total-row">
                <?php foreach ($_SESSION['scores'] as $p_scores): $total = array_sum(array_column($p_scores, 'val')); ?>
                    <td><strong><?= $total ?> / <?= $_SESSION['target_score'] ?></strong></td>
                <?php endforeach; ?>
            </tr>
        </table>
        <div style="margin-top:20px;">
            <a href="?action=end_now" class="btn" style="background:#555;">Clôturer la partie</a>
            <a href="?reset=1" style="color:#666; margin-left:20px;">Réinitialiser</a>
        </div>
    </main>
<?php endif; ?>
</body>
</html>