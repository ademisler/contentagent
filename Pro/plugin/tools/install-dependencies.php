<?php
/**
 * Dependencies Installer
 * 
 * This script helps install the required dependencies
 * for plugin functionality.
 */

if (!defined('ABSPATH')) {
    exit;
}

class ACA_Dependencies_Installer {
    
    public function __construct() {
        add_action('wp_ajax_aca_install_dependencies', array($this, 'install_dependencies'));
    }
    
    /**
     * Check if dependencies are installed
     */
    public static function are_dependencies_installed() {
        // Action Scheduler is no longer required for the unified automation system
        return file_exists(ACA_PLUGIN_PATH . 'vendor/autoload.php');
    }
    
    /**
     * Get dependency status for display
     */
    public static function get_dependency_status() {
        $vendor_exists = file_exists(ACA_PLUGIN_PATH . 'vendor/autoload.php');
        $composer_json_exists = file_exists(ACA_PLUGIN_PATH . 'composer.json');
        
        return array(
            'vendor_exists' => $vendor_exists,
            'composer_json_exists' => $composer_json_exists,
            'all_installed' => $vendor_exists,
            'can_auto_install' => function_exists('exec') && $composer_json_exists,
            'composer_available' => self::is_composer_available()
        );
    }
    
    /**
     * Check if Composer is available on the system
     */
    private static function is_composer_available() {
        if (!function_exists('exec')) {
            return false;
        }
        
        $output = array();
        $return_code = 0;
        
        // Try different composer commands
        $commands = array('composer', 'composer.phar', '/usr/local/bin/composer');
        
        foreach ($commands as $command) {
            exec($command . ' --version 2>/dev/null', $output, $return_code);
            if ($return_code === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * AJAX handler to install dependencies
     */
    public function install_dependencies() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'Insufficient permissions', 'ai-content-agent' ) );
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'aca_install_dependencies')) {
            wp_die( esc_html__( 'Security check failed', 'ai-content-agent' ) );
        }
        
        $result = $this->run_composer_install();
        
        wp_send_json($result);
    }
    
    /**
     * Run composer install
     */
    private function run_composer_install() {
        if (!function_exists('exec')) {
            return array(
                'success' => false,
                'message' => __( 'exec() function is not available. Please install dependencies manually.', 'ai-content-agent' ),
                'manual_instructions' => $this->get_manual_instructions()
            );
        }
        
        $plugin_dir = ACA_PLUGIN_PATH;
        $output = array();
        $return_code = 0;
        
        // Change to plugin directory and run composer install
        $command = "cd " . escapeshellarg($plugin_dir) . " && composer install --no-dev --optimize-autoloader 2>&1";
        
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            return array(
                'success' => true,
                'message' => __( 'Dependencies installed successfully!', 'ai-content-agent' ),
                'output' => implode("\n", $output)
            );
        } else {
            return array(
                'success' => false,
                'message' => __( 'Failed to install dependencies. Please install manually.', 'ai-content-agent' ),
                'output' => implode("\n", $output),
                'manual_instructions' => $this->get_manual_instructions()
            );
        }
    }
    
    /**
     * Get manual installation instructions
     */
    private function get_manual_instructions() {
        return array(
            'title' => __( 'Manual Installation Instructions', 'ai-content-agent' ),
            'steps' => array(
                __( '1. Connect to your server via SSH or FTP', 'ai-content-agent' ),
                sprintf( __( '2. Navigate to: %s', 'ai-content-agent' ), ACA_PLUGIN_PATH ),
                __( '3. Run: composer install --no-dev --optimize-autoloader', 'ai-content-agent' ),
                __( '4. Alternatively, download dependencies manually from GitHub', 'ai-content-agent' ),
                __( '5. Refresh this page to check installation status', 'ai-content-agent' )
            ),
            'requirements' => array(
                __( 'Composer must be installed on your server', 'ai-content-agent' ),
                __( 'PHP exec() function must be enabled', 'ai-content-agent' ),
                __( 'Write permissions to the plugin directory', 'ai-content-agent' )
            )
        );
    }
    
    /**
     * Display dependency status in admin
     */
    public static function display_dependency_status() {
        $status = self::get_dependency_status();
        
        if ($status['all_installed']) {
            echo '<div class="notice notice-success"><p><strong>' . esc_html__( '✅ Plugin Dependencies:', 'ai-content-agent' ) . '</strong> ' . esc_html__( 'All required libraries are installed and ready.', 'ai-content-agent' ) . '</p></div>';
            return;
        }
        
        echo '<div class="notice notice-warning"><p><strong>' . esc_html__( '⚠️ Plugin Dependencies:', 'ai-content-agent' ) . '</strong> ' . esc_html__( 'Required libraries are missing.', 'ai-content-agent' ) . '</p>';
        
        if ($status['can_auto_install']) {
            echo '<p><button id="aca-install-deps" class="button button-primary">' . esc_html__( 'Install Dependencies Automatically', 'ai-content-agent' ) . '</button></p>';
        } else {
            echo '<p>' . esc_html__( 'Automatic installation is not available. Please install manually:', 'ai-content-agent' ) . '</p>';
            echo '<ol>';
            echo '<li>' . esc_html__( 'Connect to your server via SSH', 'ai-content-agent' ) . '</li>';
            echo '<li>' . sprintf( esc_html__( 'Navigate to: %s', 'ai-content-agent' ), '<code>' . esc_html(ACA_PLUGIN_PATH) . '</code>' ) . '</li>';
            echo '<li>' . esc_html__( 'Run: ', 'ai-content-agent' ) . '<code>composer install --no-dev --optimize-autoloader</code></li>';
            echo '</ol>';
        }
        
        echo '</div>';
        
        // Add JavaScript for auto-installation
        if ($status['can_auto_install']) {
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('#aca-install-deps').click(function() {
                    var button = $(this);
                    button.prop('disabled', true).text('<?php echo esc_js( __( 'Installing...', 'ai-content-agent' ) ); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'aca_install_dependencies',
                        nonce: '<?php echo esc_js(wp_create_nonce('aca_install_dependencies')); ?>'
                    }, function(response) {
                        if (response.success) {
                            button.text('<?php echo esc_js( __( '✅ Installed Successfully', 'ai-content-agent' ) ); ?>');
                            location.reload();
                        } else {
                            button.prop('disabled', false).text('<?php echo esc_js( __( 'Install Dependencies', 'ai-content-agent' ) ); ?>');
                            alert('<?php echo esc_js( __( 'Installation failed:', 'ai-content-agent' ) ); ?> ' + response.message);
                            if (response.manual_instructions) {
                                console.log('<?php echo esc_js( __( 'Manual instructions:', 'ai-content-agent' ) ); ?>', response.manual_instructions);
                            }
                        }
                    }).fail(function() {
                        button.prop('disabled', false).text('<?php echo esc_js( __( 'Install Dependencies', 'ai-content-agent' ) ); ?>');
                        alert('<?php echo esc_js( __( 'Installation request failed. Please try manual installation.', 'ai-content-agent' ) ); ?>');
                    });
                });
            });
            </script>
            <?php
        }
    }
}

// Initialize the installer
new ACA_Dependencies_Installer();