<?php
/**
 * Uninstall TerzoConto.
 *
 * @package TerzoConto
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (! defined('ABSPATH')) {
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

$taxonomy = 'terzoconto_allegato_movimento';

// Recupera tutti i term_taxonomy_id della taxonomy
$term_taxonomy_ids = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
        $taxonomy
    )
);
$term_ids = $wpdb->get_col(
    $wpdb->prepare(
        "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
        $taxonomy
    )
);

if (! empty($term_taxonomy_ids)) {
    if (! empty($term_ids)) {
        $meta_ids = array_map('intval', $term_ids);
        $meta_placeholders = implode(',', array_fill(0, count($meta_ids), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ({$meta_placeholders})",
                $meta_ids
            )
        );
    }

    $ids = array_map('intval', $term_taxonomy_ids);
    $ids_placeholders = implode(',', array_fill(0, count($ids), '%d'));

    // Cancella relazioni
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$ids_placeholders})",
            $ids
        )
    );

    // Cancella taxonomy
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ({$ids_placeholders})",
            $ids
        )
    );
}

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
    $table = esc_sql($table);
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
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
