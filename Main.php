 <?php
/*
Plugin Name: Staff Booking Plugin
Description: A booking plugin for selecting staff members, services, and time slots.
Version: 1.0
Author: Your Name
*/

// Enqueue scripts and styles
function sbp_enqueue_scripts() {
    wp_enqueue_style('sbp-style', plugin_dir_url(__FILE__) . 'assets/style.css');
    wp_enqueue_script('sbp-script', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), null, true);

    wp_localize_script('sbp-script', 'sbp_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sbp_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'sbp_enqueue_scripts');

// Create custom tables on plugin activation
function sbp_create_custom_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'sbp_staff';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(50) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    $table_name = $wpdb->prefix . 'sbp_services';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        staff_id mediumint(9) NOT NULL,
        service_name varchar(50) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);

    $table_name = $wpdb->prefix . 'sbp_appointments';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        staff_id mediumint(9) NOT NULL,
        service_id mediumint(9) NOT NULL,
        date date NOT NULL,
        time varchar(10) NOT NULL,
        name varchar(50) NOT NULL,
        email varchar(50) NOT NULL,
        telephone varchar(15) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'sbp_create_custom_tables');

// Uninstall callback
function sbp_uninstall() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sbp_staff");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sbp_services");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sbp_appointments");
}
register_uninstall_hook(__FILE__, 'sbp_uninstall');

// Shortcode for booking form
function sbp_booking_form() {
    ob_start();
    ?>
    <div class="sbp-container">
        <h1>Book an Appointment</h1>
        <form id="booking-form">
            <label for="staff-select">Staff Member:</label>
            <select id="staff-select" name="staff-select" required>
                <option value="">Select Staff Member</option>
                <?php
                global $wpdb;
                $staff = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sbp_staff");
                foreach ($staff as $member) {
                    echo "<option value='{$member->id}'>{$member->name}</option>";
                }
                ?>
            </select>

            <label for="service-select">Service:</label>
            <select id="service-select" name="service-select" required>
                <option value="">Select Service</option>
            </select>

            <label for="booking-date">Date:</label>
            <input type="date" id="booking-date" name="booking-date" required>

            <div id="time-slots-container">
                <!-- Time slots will be loaded here -->
            </div>

            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>

            <label for="telephone">Telephone:</label>
            <input type="text" id="telephone" name="telephone" required>

            <button type="submit">Book</button>
        </form>

        <div id="booking-result"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('sbp_booking_form', 'sbp_booking_form');

// Handle form submissions
function sbp_handle_booking() {
    check_ajax_referer('sbp_nonce', 'nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'sbp_appointments';

    $staff_id = sanitize_text_field($_POST['staff_id']);
    $service_id = sanitize_text_field($_POST['service_id']);
    $date = sanitize_text_field($_POST['date']);
    $time = sanitize_text_field($_POST['time']);
    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $telephone = sanitize_text_field($_POST['telephone']);

    // Check if the time slot is still available
    $is_available = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE staff_id = %d AND service_id = %d AND date = %s AND time = %s",
        $staff_id, $service_id, $date, $time
    )) == 0;

    if ($is_available) {
        $result = $wpdb->insert(
            $table_name,
            array(
                'staff_id' => $staff_id,
                'service_id' => $service_id,
                'date' => $date,
                'time' => $time,
                'name' => $name,
                'email' => $email,
                'telephone' => $telephone
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );

        if ($result) {
            wp_send_json_success('Booking successful! You will receive a confirmation email shortly.');
        } else {
            wp_send_json_error('Booking failed. Please try again.');
        }
    } else {
        wp_send_json_error('The selected time slot is no longer available. Please choose another time slot.');
    }
}
add_action('wp_ajax_sbp_handle_booking', 'sbp_handle_booking');
add_action('wp_ajax_nopriv_sbp_handle_booking', 'sbp_handle_booking');

// Fetch services for a staff member
function sbp_get_services() {
    check_ajax_referer('sbp_nonce', 'nonce');

    global $wpdb;
    $staff_id = sanitize_text_field($_POST['staff_id']);

    $services = $wpdb->get_results($wpdb->prepare(
        "SELECT id, service_name FROM {$wpdb->prefix}sbp_services WHERE staff_id = %d",
        $staff_id
    ));

    wp_send_json_success($services);
}
add_action('wp_ajax_sbp_get_services', 'sbp_get_services');
add_action('wp_ajax_nopriv_sbp_get_services', 'sbp_get_services');

// Fetch available time slots for a service
function sbp_get_available_slots() {
    check_ajax_referer('sbp_nonce', 'nonce');

    global $wpdb;
    $staff_id = sanitize_text_field($_POST['staff_id']);
    $service_id = sanitize_text_field($_POST['service_id']);
    $date = sanitize_text_field($_POST['date']);

    $booked_slots = $wpdb->get_col($wpdb->prepare(
        "SELECT time FROM {$wpdb->prefix}sbp_appointments WHERE staff_id = %d AND service_id = %d AND date = %s",
        $staff_id, $service_id, $date
    ));

    $all_slots = array('09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00');
    $available_slots = array_diff($all_slots, $booked_slots);

    wp_send_json_success(array_values($available_slots));
}
add_action('wp_ajax_sbp_get_available_slots', 'sbp_get_available_slots');
add_action('wp_ajax_nopriv_sbp_get_available_slots', 'sbp_get_available_slots');
