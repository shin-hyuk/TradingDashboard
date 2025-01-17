<?php
$connect = new mysqli('192.168.10.177', 'jason', 'Abc123456', 'stat_unisoft_hk', 3306);

// Check the connection
if ($connect->connect_error) {
    die("Database connection failed: " . $connect->connect_error);
}

// Create the backtests table if not already present
$create_backtests_sql = "
CREATE TABLE IF NOT EXISTS backtests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    file_link TEXT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";
if (!$connect->query($create_backtests_sql)) {
    die("Failed to create backtests table: " . $connect->error);
}
// Handle different POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'upload') {
        // Add a new backtest with name, description, and file link
        $name = $_POST['name'] ?? 'Untitled';
        $description = $_POST['description'] ?? '';
        $file_link = $_POST['file_link'] ?? '';

        // Insert the backtest row
        $stmt = $connect->prepare("INSERT INTO backtests (name, description, file_link) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $description, $file_link);
        $stmt->execute();
        $stmt->close();

        echo "<h1>Backtest added successfully!</h1>";
    }

    if ($action === 'delete') {
        // Delete a backtest
        $id = $_POST['id'];
        $stmt = $connect->prepare("DELETE FROM backtests WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo "<h1>Backtest deleted successfully!</h1>";
    }
}

// Fetch all backtests with associated files
$query = "SELECT id, name, description, file_link FROM backtests ORDER BY id";
$result = $connect->query($query);

$backtests = [];
while ($row = $result->fetch_assoc()) {
    $backtests[] = $row;
}
?>


<br><br><br><br><br><br>
<!-- Backtest Entry Form -->
<form method="post" style="text-align: left; max-width: 500px; margin: 0 auto;">
    <input type="hidden" name="action" value="upload">

    <label for="name" style="display: block; text-align: left; margin-bottom: 5px;">Name:</label>
    <input type="text" id="name" name="name" style="width: 100%; margin-bottom: 15px;" required>

    <label for="description" style="display: block; text-align: left; margin-bottom: 5px;">Description:</label>
    <textarea id="description" name="description" rows="4" style="width: 100%; resize: both; margin-bottom: 15px;" required></textarea>

    <label for="file_link" style="display: block; text-align: left; margin-bottom: 5px;">File Link:</label>
    <input type="url" id="file_link" name="file_link" style="width: 100%; margin-bottom: 15px;" placeholder="https://example.com" required>

    <input type="submit" value="Add Backtest" style="margin-top: 10px;">
</form>

<br><br><br><br><br><br>

<!-- Backtests Table -->
<div style="max-width: 800px; margin: 0 auto;">
    <?php foreach ($backtests as $backtest): ?>
        <div style="display: flex; align-items: center; border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; gap: 10px;">
            <!-- Title -->
            <div style="flex: 2; font-weight: bold;">
                <?= htmlspecialchars($backtest['name']) ?>
            </div>

            <!-- Description -->
            <div style="flex: 5; text-align: left;"> <!-- Increased flex for more space -->
                <?= htmlspecialchars($backtest['description']) ?>
            </div>

            <!-- Actions -->
            <div style="flex: 2; text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
                <!-- Link Button -->
                <a href="<?= htmlspecialchars($backtest['file_link']) ?>" target="_blank">
                    <button type="button" style="white-space: nowrap;">Link</button>
                </a>

                <!-- Delete Button -->
                <form method="post" style="margin: 0;">
                    <input type="hidden" name="id" value="<?= $backtest['id'] ?>">
                    <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure?')">Delete</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
