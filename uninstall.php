<?php
/**
 * Uninstall TerzoConto.
 *
 * @package TerzoConto
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Rimozione esplicita dei termini taxonomy del plugin (e relative relazioni).
$terms = get_terms(
    [
        'taxonomy' => 'terzoconto_allegato_movimento',
        'hide_empty' => false,
        'fields' => 'ids',
    ]
);

if (! is_wp_error($terms) && ! empty($terms)) {
    foreach ($terms as $term_id) {
        wp_delete_term((int) $term_id, 'terzoconto_allegato_movimento');
    }
}

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
        'terzoconto_allegato_movimento'
    )
);

$tables = [
    $wpdb->prefix . 'terzoconto_movimenti',
    $wpdb->prefix . 'terzoconto_categorie_associazione',
    $wpdb->prefix . 'terzoconto_categorie_modello_d',
    $wpdb->prefix . 'terzoconto_conti',
    $wpdb->prefix . 'terzoconto_raccolte_fondi',
    $wpdb->prefix . 'terzoconto_anagrafiche',
    $wpdb->prefix . 'terzoconto_settings',
    $wpdb->prefix . 'terzoconto_comunicazioni_ae',
    $wpdb->prefix . 'terzoconto_regole',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

$options_to_delete = [
    'terzoconto_db_version',
    'terzoconto_plugin_version',
    'terzoconto_seed_version',
    'terzoconto_migrating',
];

foreach ($options_to_delete as $option_name) {
    delete_option($option_name);
}
