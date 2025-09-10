<?php
// Asset configuration - Set to true for offline mode, false for CDN mode
$offline_mode = true;

if ($offline_mode) {
    // Local assets
    echo '<link href="assets/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="assets/css/fontawesome.min.css" rel="stylesheet">';
    echo '<script src="assets/js/tailwind-cdn.js"></script>';
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
    echo '<link href="assets/css/custom.css" rel="stylesheet">';
    echo '<script src="assets/js/bootstrap.bundle.min.js"></script>';
} else {
    // CDN assets (for when you have internet)
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
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
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
}
?>

<?php
// Chart.js Assets - Include this function in pages that need charts
function includeChartAssets($offline_mode = true) {
    if ($offline_mode) {
        echo '<script src="assets/js/chart.min.js"></script>';
        echo '<script src="assets/js/chartjs-plugin-datalabels.min.js"></script>';
    } else {
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0"></script>';
        echo '<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>';
    }
}
?>
