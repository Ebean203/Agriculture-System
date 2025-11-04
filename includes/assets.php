<?php
// Asset configuration - Auto-detect online/offline mode
// You can override with ?offline=1 or ?offline=0 in the URL for manual toggle
$offline_mode = isset($_GET['offline']) ? ($_GET['offline'] == '1') : null;
if ($offline_mode === null) {
    // Default to CDN, but fallback to offline if CDN CSS fails to load
    $offline_mode = false;
}
// Simple cache-busting for custom.css so style changes are visible immediately
$customCssRel = 'assets/css/custom.css';
$customCssAbs = __DIR__ . '/../assets/css/custom.css';
$customCssVer = file_exists($customCssAbs) ? filemtime($customCssAbs) : time();
$toastJsRel = 'assets/js/agri-toast.js';
$toastJsAbs = __DIR__ . '/../assets/js/agri-toast.js';
$toastJsVer = file_exists($toastJsAbs) ? filemtime($toastJsAbs) : time();

if ($offline_mode) {
    // Local assets
    echo '<link href="assets/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="assets/css/fontawesome.min.css" rel="stylesheet">';
    // Load local Tailwind CDN build (kept for offline compatibility).
    // The script can be noisy (console.warn/info); we add a small APP_DEBUG toggle
    // so warnings can be suppressed in non-debug environments. Run the suppression
    // BEFORE loading the tailwind bundle so any internal console calls are silenced.
    echo '<script>window.APP_DEBUG = window.APP_DEBUG || false;</script>';
    echo '<script>
        if (!window.APP_DEBUG && typeof console !== "undefined") {
            // Silence non-critical console methods to reduce noise in production
            console._orig = console._orig || { warn: console.warn, info: console.info, log: console.log, debug: console.debug };
            console.warn = function(){};
            console.info = function(){};
            console.log = function(){};
            console.debug = function(){};
        }
    </script>';
    echo '<script src="assets/js/tailwind-cdn.js"></script>';
    // Tailwind config should be set after the bundle loads
    echo '<script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "agri-green": "#16a34a",
                        "agri-dark": "#16a34a",
                        "agri-light": "#dcfce7"
                    }
                }
            }
        }
    </script>';
    // Always include our custom overrides after frameworks
    echo '<link href="' . $customCssRel . '?v=' . $customCssVer . '" rel="stylesheet">';
    echo '<script src="assets/js/jquery.min.js"></script>';
    echo '<script src="assets/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="' . $toastJsRel . '?v=' . $toastJsVer . '"></script>';
} else {
    // CDN assets (for when you have internet)
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" onerror="this.onerror=null;this.href=\'assets/css/bootstrap.min.css\';">';
    echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" onerror="this.onerror=null;this.href=\'assets/css/fontawesome.min.css\';">';
    // When using CDN in development, you can still control console verbosity via APP_DEBUG
    echo '<script>window.APP_DEBUG = window.APP_DEBUG || false;</script>';
    // Suppress console early so the CDN script cannot log warnings when APP_DEBUG is false
    echo '<script>
        if (!window.APP_DEBUG && typeof console !== "undefined") {
            console._orig = console._orig || { warn: console.warn, info: console.info, log: console.log, debug: console.debug };
            console.warn = function(){};
            console.info = function(){};
            console.log = function(){};
            console.debug = function(){};
        }
    </script>';
    echo '<script src="https://cdn.tailwindcss.com" onerror="this.onerror=null;this.src=\'assets/js/tailwind-cdn.js\';"></script>';
    echo '<script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "agri-green": "#16a34a",
                        "agri-dark": "#16a34a", 
                        "agri-light": "#dcfce7"
                    }
                }
            }
        }
    </script>';
    // Include our custom overrides CSS after Bootstrap & FA even when using CDN
    echo '<link href="' . $customCssRel . '?v=' . $customCssVer . '" rel="stylesheet">';
    echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js" onerror="this.onerror=null;this.src=\'assets/js/jquery.min.js\';"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" onerror="this.onerror=null;this.src=\'assets/js/bootstrap.bundle.min.js\';"></script>';
    echo '<script src="' . $toastJsRel . '?v=' . $toastJsVer . '"></script>';
}
?>

<?php
// Chart.js Assets - Include this function in pages that need charts
if (!function_exists('includeChartAssets')) {
    function includeChartAssets($offline_mode = true) {
        if ($offline_mode) {
            echo '<script src="assets/js/chart.min.js"></script>';
            echo '<script src="assets/js/chartjs-plugin-datalabels.min.js"></script>';
        } else {
            echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>';
            echo '<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>';
        }
    }
}
?>
