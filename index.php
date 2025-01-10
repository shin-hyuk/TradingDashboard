<?php
// Define an array of cryptocurrency pairs
$pairs = [
    "BTCUSDT" => "BTC",
    "ETHUSDT" => "ETH",
];

?>
<!DOCTYPE html>
<html lang="en">
<body>
    <?php foreach ($pairs as $pair => $label): ?>
        <a href="stat.php?pair=<?= $pair ?>">
            <button><?= $label ?></button>
        </a>
    <?php endforeach; ?>
</body>
</html>
