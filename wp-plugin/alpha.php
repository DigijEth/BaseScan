<?php
/*
Plugin Name: Crypto Scanner
Description: Scans the blockchain for new pairs and analyzes their contracts for exploits.
Version: 1.0
Author: Your Name
*/

// Add settings page
add_action('admin_menu', 'cs_add_settings_page');
function cs_add_settings_page() {
    add_options_page(
        'Crypto Scanner Settings',
        'Crypto Scanner',
        'manage_options',
        'crypto-scanner-settings',
        'cs_settings_page_content'
    );
}

// Settings page content
function cs_settings_page_content() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if (isset($_POST['cs_settings_submit'])) {
        check_admin_referer('cs_settings_nonce');
        update_option('cs_bscscan_api_key', sanitize_text_field($_POST['cs_bscscan_api_key']));
        update_option('cs_openai_api_key', sanitize_text_field($_POST['cs_openai_api_key']));
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $bscscan_api_key = get_option('cs_bscscan_api_key', '');
    $openai_api_key  = get_option('cs_openai_api_key', '');

    echo '<div class="wrap">';
    echo '<h1>Crypto Scanner Settings</h1>';
    echo '<form method="post">';
    wp_nonce_field('cs_settings_nonce');
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="cs_bscscan_api_key">BSCScan API Key</label></th>';
    echo '<td><input type="text" id="cs_bscscan_api_key" name="cs_bscscan_api_key" value="' . esc_attr($bscscan_api_key) . '" class="regular-text"></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="cs_openai_api_key">OpenAI API Key</label></th>';
    echo '<td><input type="text" id="cs_openai_api_key" name="cs_openai_api_key" value="' . esc_attr($openai_api_key) . '" class="regular-text"></td>';
    echo '</tr>';
    echo '</table>';
    submit_button('Save Settings', 'primary', 'cs_settings_submit');
    echo '</form>';

    // Display the scanned results
    echo '<h2>Crypto Scanner Results</h2>';
    cs_display_results();

    echo '</div>';
}

// Function to display results
function cs_display_results() {
    $pairs = cs_get_new_pairs();

    if (!empty($pairs)) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Pair</th><th>Status</th></tr></thead><tbody>';

        foreach ($pairs as $pair) {
            $status = cs_analyze_contract($pair['contract_address']);
            echo '<tr>';
            echo '<td>' . esc_html($pair['name']) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>No new pairs found or unable to fetch data.</p>';
    }
}

// Fetch new pairs
function cs_get_new_pairs() {
    $bscscan_api_key = get_option('cs_bscscan_api_key', '');
    if (empty($bscscan_api_key)) {
        return [];
    }

    // API endpoint to fetch new pairs (replace with correct endpoint)
    $api_url = 'https://api.bscscan.com/api?module=token&action=tokenlist&apikey=' . $bscscan_api_key;

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return [];
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['status']) || $body['status'] != '1') {
        return [];
    }

    $pairs = [];
    foreach ($body['result'] as $token) {
        $pairs[] = [
            'name'             => $token['tokenName'],
            'contract_address' => $token['contractAddress']
        ];
    }

    // Limit to latest 10 pairs
    return array_slice($pairs, 0, 10);
}

// Analyze contract
function cs_analyze_contract($contract_address) {
    // Check cache
    $cached_status = get_transient('cs_status_' . $contract_address);
    if ($cached_status !== false) {
        return $cached_status;
    }

    $code = cs_fetch_contract_code($contract_address);

    if (empty($code)) {
        return 'Unable to fetch contract code.';
    }

    $analysis = cs_openai_analyze($code);
    $status   = cs_evaluate_analysis($analysis);

    // Cache result
    set_transient('cs_status_' . $contract_address, $status, 12 * HOUR_IN_SECONDS);

    return $status;
}

// Fetch contract code
function cs_fetch_contract_code($contract_address) {
    $bscscan_api_key = get_option('cs_bscscan_api_key', '');
    if (empty($bscscan_api_key)) {
        return '';
    }

    $api_url = 'https://api.bscscan.com/api?module=contract&action=getsourcecode&address=' . $contract_address . '&apikey=' . $bscscan_api_key;

    $response = wp_remote_get($api_url);

    if (is_wp_error($response)) {
        return '';
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['status']) || $body['status'] != '1' || empty($body['result'][0]['SourceCode'])) {
        return '';
    }

    return $body['result'][0]['SourceCode'];
}

// Analyze code with OpenAI
function cs_openai_analyze($code) {
    $openai_api_key = get_option('cs_openai_api_key', '');
    if (empty($openai_api_key)) {
        return 'OpenAI API key not set.';
    }

    $endpoint = 'https://api.openai.com/v1/chat/completions';

    $messages = [
        [
            'role'    => 'system',
            'content' => 'You are a smart contract security auditor. Analyze the following smart contract code for known exploits and high-risk issues.'
        ],
        [
            'role'    => 'user',
            'content' => $code
        ]
    ];

    $data = [
        'model'        => 'gpt-4',
        'messages'     => $messages,
        'max_tokens'   => 500,
        'temperature'  => 0.2,
    ];

    $args = [
        'body'    => json_encode($data),
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $openai_api_key,
        ],
        'timeout' => 60,
    ];

    $response = wp_remote_post($endpoint, $args);

    if (is_wp_error($response)) {
        return 'Error in OpenAI API request.';
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['choices'][0]['message']['content'] ?? 'No analysis available.';
}

// Evaluate analysis
function cs_evaluate_analysis($analysis) {
    $lowercase_analysis = strtolower($analysis);
    if (
        strpos($lowercase_analysis, 'vulnerability') !== false ||
        strpos($lowercase_analysis, 'exploit') !== false ||
        strpos($lowercase_analysis, 'high-risk') !== false ||
        strpos($lowercase_analysis, 'scam') !== false
    ) {
        return '⚠️ Possible Scam';
    } else {
        return '✅ Safe';
    }
}

// Register the widget
add_action('widgets_init', 'cs_register_widget');
function cs_register_widget() {
    register_widget('Crypto_Scanner_Widget');
}

// Define the widget class
class Crypto_Scanner_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'crypto_scanner_widget',
            __('Crypto Scanner Widget', 'crypto-scanner'),
            array('description' => __('Displays the latest crypto pairs and their risk status.', 'crypto-scanner'))
        );
    }

    // Front-end display
    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title']
                . apply_filters('widget_title', $instance['title'])
                . $args['after_title'];
        }

        cs_display_results();

        echo $args['after_widget'];
    }

    // Widget settings form
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Crypto Scanner', 'crypto-scanner');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_attr_e('Title:', 'crypto-scanner'); ?>
            </label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    // Save widget settings
    public function update($new_instance, $old_instance) {
        $instance          = array();
        $instance['title'] = (!empty($new_instance['title']))
            ? sanitize_text_field($new_instance['title'])
            : '';
        return $instance;
    }
}

// Add shortcode (optional)
add_shortcode('crypto_scanner', 'cs_shortcode_display_results');
function cs_shortcode_display_results() {
    ob_start();
    cs_display_results();
    return ob_get_clean();
}
