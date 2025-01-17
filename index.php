<?php
// Define an array of cryptocurrency pairs
$pairs = [
    "BTCUSDT" => "BTC",
    "ETHUSDT" => "ETH",
    // Add more pairs here as needed
];
?>
<!DOCTYPE html>
<html lang="en">
<body>
<?php foreach ($pairs as $pair => $label): ?>
<a href="stat.php?pair=<?= $pair ?>"><button><?= $label ?></button></a>
<?php endforeach; ?>
<a href="backtest.php"><button>Backtest</button></a>
</body>
</html>