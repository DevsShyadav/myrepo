<?php
/**
 * WhatsApp CRM Dashboard
 * Premium 3-Column SaaS Layout
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Initialize session
initSession();

// Get socket URL from settings
$socketUrl = getSetting('socket_url', SOCKET_URL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN (utility classes only) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#10B981',
                        'primary-dark': '#059669'
                    }
                }
            }
        };
    </script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/app.css">

    <!-- Socket.io Client -->
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
</head>
<body data-socket-url="<?php echo htmlspecialchars($socketUrl); ?>">

    <!-- Main 3-Column Layout -->
    <div class="app-layout">

        <!-- LEFT: Sidebar -->
        <?php include __DIR__ . '/components/sidebar.php'; ?>

        <!-- MIDDLE: Leads Panel -->
        <?php include __DIR__ . '/components/leads_panel.php'; ?>

        <!-- RIGHT: Chat Panel -->
        <?php include __DIR__ . '/components/chat_panel.php'; ?>

    </div>

    <!-- Modals -->
    <?php include __DIR__ . '/components/import_modal.php'; ?>
    <?php include __DIR__ . '/components/settings_modal.php'; ?>
    <?php include __DIR__ . '/components/qr_modal.php'; ?>

    <!-- Toast -->
    <?php include __DIR__ . '/components/toast.php'; ?>

    <!-- JavaScript Modules -->
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/notifications.js"></script>
    <script src="assets/js/socket.js"></script>
    <script src="assets/js/leads.js"></script>
    <script src="assets/js/chat.js"></script>
    <script src="assets/js/campaign.js"></script>
    <script src="assets/js/settings.js"></script>
    <script src="assets/js/app.js"></script>

</body>
</html>
