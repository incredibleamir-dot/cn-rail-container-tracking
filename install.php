<?php
/**
 * CN Track - Installation Script
 * Creates database + default admin user
 * One-time use only
 */

define('APP_DIR', __DIR__);
define('DB_PATH', APP_DIR . '/data/cntrack.db');
define('APP_NAME', 'CN Track');
define('DEBUG_MODE', true);

if (file_exists(DB_PATH)) {
    echo '<!DOCTYPE html><html><head><title>' . APP_NAME . ' - Installed</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">';
    echo '<div class="text-center">';
    echo '<h2 class="text-muted">' . APP_NAME . ' is already installed.</h2>';
    echo '<p class="text-muted">Delete <code>data/cntrack.db</code> to reinstall.</p>';
    echo '<a href="index.php" class="btn btn-primary">Go to Login</a>';
    echo '</div></body></html>';
    exit;
}

$success = '';
$error   = '';
$created = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminPin  = trim($_POST['admin_pin'] ?? '');

    if (empty($adminName)) {
        $error = 'Admin name is required.';
    } elseif (empty($adminPin) || strlen($adminPin) < 4) {
        $error = 'PIN must be at least 4 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9]+$/', $adminPin)) {
        $error = 'PIN must be alphanumeric only.';
    } else {
        try {
            $dataDir = dirname(DB_PATH);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0777, true);
            }
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');

            $schema = file_get_contents(__DIR__ . '/db/schema.sql');
            $pdo->exec($schema);

            $stmt = $pdo->prepare('INSERT INTO users (name, pin, role) VALUES (?, ?, ?)');
            $stmt->execute([$adminName, $adminPin, 'admin']);

            $success = 'Installation complete! Admin account created.';
            $created = true;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1e2125 0%, #2c3e50 100%); min-height: 100vh; }
        .install-card { background: #fff; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 480px; margin: 0 auto; }
        .brand-bar { background: #DA291C; height: 5px; border-radius: 20px 20px 0 0; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-4">
    <div class="install-card w-100">
        <div class="brand-bar"></div>
        <div class="p-5">
            <div class="text-center mb-4">
                <i class="fas fa-train fa-3x text-danger mb-3"></i>
                <h3 class="fw-bold"><?php echo APP_NAME; ?></h3>
                <p class="text-muted">Initial Setup</p>
            </div>

            <?php if ($success && $created): ?>
                <div class="alert alert-success text-center">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
                <a href="index.php" class="btn btn-danger w-100 fw-bold">Go to Login</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Admin Name</label>
                        <input type="text" name="admin_name" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['admin_name'] ?? ''); ?>"
                               placeholder="e.g. Amir Arshad">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Admin PIN</label>
                        <input type="text" name="admin_pin" class="form-control font-monospace" required
                               value="<?php echo htmlspecialchars($_POST['admin_pin'] ?? ''); ?>"
                               placeholder="Alphanumeric, min 4 chars" maxlength="32">
                        <div class="form-text">Letters and numbers only. This is how you will log in.</div>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold py-2">
                        <i class="fas fa-rocket me-2"></i>Install &amp; Create Admin
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
