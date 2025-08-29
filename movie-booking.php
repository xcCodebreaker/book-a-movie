<?php
/*
Plugin Name: Book a movie
Description: A very simple movie theatre booking system.
Version: 1.2
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
