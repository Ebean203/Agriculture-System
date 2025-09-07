<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Mode Switcher</title>
    <?php include 'includes/assets.php'; ?>
    
    <style>
        body { background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%); min-height: 100vh; }
        .card { border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .btn-custom { background: linear-gradient(45deg, #16a34a, #22c55e); border: none; }
        .btn-custom:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white text-center">
                        <h3><i class="bi bi-gear"></i> Lagonglong FARMS - Asset Configuration</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $assets_file = 'includes/assets.php';
                        $current_mode = 'online'; // default
                        
                        if (file_exists($assets_file)) {
                            $content = file_get_contents($assets_file);
                            if (strpos($content, '$offline_mode = true') !== false) {
                                $current_mode = 'offline';
                            }
                        }
                        
                        if ($_POST['action'] ?? false) {
                            $new_mode = $_POST['mode'];
                            $content = file_get_contents($assets_file);
                            
                            if ($new_mode === 'offline') {
                                $content = str_replace('$offline_mode = false', '$offline_mode = true', $content);
                                $message = "✅ Switched to OFFLINE mode - Your app will now work without internet!";
                                $alert_class = "alert-success";
                            } else {
                                $content = str_replace('$offline_mode = true', '$offline_mode = false', $content);
                                $message = "🌐 Switched to ONLINE mode - Using CDN for latest assets!";
                                $alert_class = "alert-info";
                            }
                            
                            file_put_contents($assets_file, $content);
                            $current_mode = $new_mode;
                            
                            echo "<div class='alert $alert_class alert-dismissible fade show' role='alert'>
                                    $message
                                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                  </div>";
                        }
                        ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Current Mode</h5>
                                <div class="alert <?= $current_mode === 'offline' ? 'alert-warning' : 'alert-info' ?>">
                                    <strong><?= strtoupper($current_mode) ?> MODE</strong><br>
                                    <?= $current_mode === 'offline' 
                                        ? '📱 Works without internet connection' 
                                        : '🌐 Requires internet connection' ?>
                                </div>
                                
                                <h6>Asset Status:</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        Bootstrap CSS
                                        <span class="badge bg-<?= $current_mode === 'offline' ? 'warning' : 'primary' ?>">
                                            <?= $current_mode === 'offline' ? 'Local' : 'CDN' ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        Tailwind CSS
                                        <span class="badge bg-<?= $current_mode === 'offline' ? 'warning' : 'primary' ?>">
                                            <?= $current_mode === 'offline' ? 'Local' : 'CDN' ?>
                                        </span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        Font Awesome
                                        <span class="badge bg-<?= $current_mode === 'offline' ? 'warning' : 'primary' ?>">
                                            <?= $current_mode === 'offline' ? 'Local' : 'CDN' ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="col-md-6">
                                <h5>Switch Mode</h5>
                                <form method="POST">
                                    <input type="hidden" name="action" value="switch">
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="mode" value="offline" 
                                               id="offline" <?= $current_mode === 'offline' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="offline">
                                            <strong>📱 Offline Mode</strong><br>
                                            <small class="text-muted">Works without internet. Uses local files.</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="mode" value="online" 
                                               id="online" <?= $current_mode === 'online' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="online">
                                            <strong>🌐 Online Mode</strong><br>
                                            <small class="text-muted">Uses CDN. Always up-to-date assets.</small>
                                        </label>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-custom btn-lg w-100 text-white">
                                        Apply Changes
                                    </button>
                                </form>
                                
                                <div class="mt-3">
                                    <a href="test-assets.php" class="btn btn-outline-success w-100">
                                        Test Current Configuration
                                    </a>
                                </div>
                                
                                <div class="mt-2">
                                    <a href="farmers.php" class="btn btn-outline-primary w-100">
                                        Go to Application
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($offline_mode): ?>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <?php else: ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
</body>
</html>
