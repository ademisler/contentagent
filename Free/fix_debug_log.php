<?php
// Fix debug log function
$file = '/workspace/Free/plugin/ai-content-agent.php';
$content = file_get_contents($file);

// Replace the debug log function
$pattern = '/function aicoagac_debug_log\([^)]*\)\s*\{[^}]*error_log[^}]*\}/s';
$replacement = 'function aicoagac_debug_log($message) {
    // Debug logging disabled in production
    // To enable logging, use WP_DEBUG_LOG in wp-config.php
    return;
}';

$content = preg_replace($pattern, $replacement, $content);
file_put_contents($file, $content);
echo "Debug log function fixed\n";