<?php
/*
Plugin Name: Book a movie
Description: A very simple movie theatre booking system.
Version: 1.0
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


