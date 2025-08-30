<?php
/*
Plugin Name: Book a movie
Description: A very simple movie theatre booking system.
Version: 1.3
Author: Abdullah
*/

defined('ABSPATH') or die("No direct access");

function smb_install() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}movies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        showtime VARCHAR(100) NOT NULL
    ) $charset;");

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        movie_id INT NOT NULL,
        email VARCHAR(200) NOT NULL,
        seats INT NOT NULL,
        FOREIGN KEY (movie_id) REFERENCES {$wpdb->prefix}movies(id) ON DELETE CASCADE
    ) $charset;");
}
register_activation_hook(__FILE__, 'smb_install');

// This one displays the movie list only
function smb_movie_list() {
    global $wpdb;
    $movies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}movies");
    if (!$movies) return "<p>No movies available.</p>";

    $out = "<h2>Now Showing</h2><ul>";
    foreach ($movies as $m) {
        $out .= "<li><b>{$m->title}</b> - {$m->showtime}</li>";
    }
    $out .= "</ul>";
    return $out;
}
add_shortcode('movie_list', 'smb_movie_list');

function smb_admin_menu() {
    add_menu_page(
        "Movie Booking",
        "Movie Booking",
        "manage_options",
        "smb_manage_movies",
        "smb_manage_movies_page",
        "dashicons-video-alt2"
    );
}
add_action("admin_menu", "smb_admin_menu");

// Admin: Manage movies
function smb_manage_movies_page() {
    global $wpdb;

    // Delete movie
    if (isset($_GET['delete_movie'])) {
        $del_id = intval($_GET['delete_movie']);
        if ($del_id && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'smb_delete_movie_' . $del_id)) {
            $wpdb->delete($wpdb->prefix . "movies", ["id" => $del_id]);
            echo "<div class='updated'><p>Movie deleted successfully.</p></div>";
        }
    }

    // Add movie
    if (isset($_POST['smb_add_movie']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'smb_add_movie')) {
        $title = sanitize_text_field($_POST['title']);
        $showtime = sanitize_text_field($_POST['showtime']);
        if ($title !== '' && $showtime !== '') {
            $wpdb->insert($wpdb->prefix . "movies", ["title" => $title, "showtime" => $showtime]);
            echo "<div class='updated'><p>Movie added successfully.</p></div>";
        }
    }

    // Display movies
    $movies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}movies ORDER BY id DESC");

    echo "<div class='wrap'><h2>Manage Movies</h2>
        <form method='post'>";
    wp_nonce_field('smb_add_movie');
    echo "    <input type='text' name='title' placeholder='Movie Title' required>
            <input type='text' name='showtime' placeholder='Showtime (e.g., 7:00 PM)' required>
            <button type='submit' name='smb_add_movie' class='button button-primary'>Add Movie</button>
        </form>
        <h3>Current Movies</h3>";

    if ($movies) {
        echo "<table class='widefat fixed'>
            <thead><tr>
                <th>ID</th>
                <th>Title</th>
                <th>Showtime</th>
                <th>Action</th>
            </tr></thead><tbody>";
        foreach ($movies as $m) {
            $delete_url = wp_nonce_url(admin_url("admin.php?page=smb_manage_movies&delete_movie={$m->id}"), 'smb_delete_movie_' . $m->id);
            echo "<tr>
                <td>" . esc_html($m->id) . "</td>
                <td>" . esc_html($m->title) . "</td>
                <td>" . esc_html($m->showtime) . "</td>
                <td><a href='" . esc_url($delete_url) . "' class='button button-danger' onclick='return confirm(\"Are you sure you want to delete this movie?\");'>Delete</a></td>
            </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>No movies available yet.</p>";
    }
    echo "</div>";
}

// Booking form
function smb_booking_form() {
    global $wpdb;
    $msg = "";

    if ($_POST && isset($_POST['smb_book'])) {
        $wpdb->insert(
            $wpdb->prefix . "bookings",
            [
                "movie_id" => intval($_POST['movie_id']),
                "email" => sanitize_email($_POST['email']),
                "seats" => intval($_POST['seats'])
            ]
        );
        $msg = "<p style='color:green;'>Booking successful!</p>";
    }

    $movies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}movies");
    if (!$movies) return "<p>No movies available for booking.</p>";

    $form = "$msg<form method='POST'>
        <label>Select Movie:</label><br>
        <select name='movie_id'>";
    foreach ($movies as $m) {
        $form .= "<option value='{$m->id}'>{$m->title} - {$m->showtime}</option>";
    }
    $form .= "</select><br><br>
        <label>Email:</label><br>
        <input type='email' name='email' required><br><br>
        <label>Seats:</label><br>
        <input type='number' name='seats' min='1' value='1' required><br><br>
        <button type='submit' name='smb_book'>Book Now</button>
    </form>";
    return $form;
}
add_shortcode('booking_form', 'smb_booking_form');

// Booking history for users
function smb_profile() {
    global $wpdb;
    $out = "";

    if ($_POST && isset($_POST['smb_check'])) {
        $email = sanitize_email($_POST['email']);
        $bookings = $wpdb->get_results(
            $wpdb->prepare("SELECT b.id, m.title, m.showtime, b.seats 
                            FROM {$wpdb->prefix}bookings b
                            JOIN {$wpdb->prefix}movies m ON b.movie_id=m.id
                            WHERE b.email=%s", $email)
        );
        if ($bookings) {
            $out .= "<h3>Your Bookings</h3><ul>";
            foreach ($bookings as $b) {
                $out .= "<li>{$b->title} ({$b->showtime}) - Seats: {$b->seats}</li>";
            }
            $out .= "</ul>";
        } else {
            $out .= "<p>No bookings found for $email</p>";
        }
    }

    $out .= "<form method='POST'>
        <label>Enter Email to See Bookings:</label><br>
        <input type='email' name='email' required>
        <button type='submit' name='smb_check'>Check</button>
    </form>";

    return $out;
}
add_shortcode('profile', 'smb_profile');

// Admin: View bookings
function smb_view_bookings_page() {
    global $wpdb;

    if (isset($_GET['delete_booking'])) {
        $del_id = intval($_GET['delete_booking']);
        if ($del_id && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'smb_delete_booking_' . $del_id)) {
            $wpdb->delete($wpdb->prefix . "bookings", ["id" => $del_id]);
            echo "<div class='updated'><p>Booking deleted successfully.</p></div>";
        }
    }

    $bookings = $wpdb->get_results(
        "SELECT b.id, b.email, b.seats, m.title, m.showtime
         FROM {$wpdb->prefix}bookings b
         JOIN {$wpdb->prefix}movies m ON b.movie_id = m.id
         ORDER BY b.id DESC"
    );

    echo "<div class='wrap'><h2>All Bookings</h2>";
    if ($bookings) {
        echo "<table class='widefat fixed'>
            <thead><tr>
                <th>ID</th>
                <th>Email</th>
                <th>Movie</th>
                <th>Showtime</th>
                <th>Seats</th>
                <th>Action</th>
            </tr></thead><tbody>";
        foreach ($bookings as $b) {
            $delete_url = wp_nonce_url(admin_url("admin.php?page=smb_view_bookings&delete_booking={$b->id}"), 'smb_delete_booking_' . $b->id);
            echo "<tr>
                <td>" . esc_html($b->id) . "</td>
                <td>" . esc_html($b->email) . "</td>
                <td>" . esc_html($b->title) . "</td>
                <td>" . esc_html($b->showtime) . "</td>
                <td>" . esc_html($b->seats) . "</td>
                <td><a href='" . esc_url($delete_url) . "' class='button button-danger' onclick='return confirm(\"Are you sure you want to delete this booking?\");'>Delete</a></td>
            </tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>No bookings yet.</p>";
    }
    echo "</div>";
}

