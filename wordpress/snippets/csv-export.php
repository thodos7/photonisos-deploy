<?php
/**
 * Photonisos - Εξαγωγή CSV
 *
 * Μόνο για διαχειριστές: /wp-admin/index.php?photo_export=1 (12 στήλες, UTF-8 με BOM).
 * Στο πρόσθετο Code Snippets επικολλάται ο κώδικας ΧΩΡΙΣ τη γραμμή <?php.
 */

add_action('admin_init', function () {
    if (!current_user_can('manage_options') || !isset($_GET['photo_export'])) {
        return;
    }

    // Καθαρίζουμε τυχόν έξοδο και κρύβουμε προειδοποιήσεις PHP, ώστε να μη μπουν μέσα στο CSV
    while (ob_get_level()) {
        ob_end_clean();
    }
    @ini_set('display_errors', '0');
    error_reporting(0);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=photonisos_export_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // BOM: για σωστά ελληνικά στο Excel

    fputcsv($output, [
        'ID', 'Τίτλος', 'Permalink', 'Περιγραφή', 'Τοποθεσία', 'Χρονολογία',
        'Εικονιζόμενα Πρόσωπα', 'Θεματική Κατηγορία', 'Πηγή / Ιδιοκτήτης',
        'Τύπος Φωτογραφίας', 'Κατάσταση Τεκμηρίωσης', 'Δικαιώματα / Άδεια Χρήσης',
    ]);

    // Μετατρέπει κάθε τιμή (κείμενο, πίνακας, αντικείμενο) σε απλό κείμενο
    $flat = function ($v) use (&$flat) {
        if (is_array($v)) {
            if (isset($v['label'])) {
                return $flat($v['label']);
            }
            $parts = [];
            foreach ($v as $item) {
                $parts[] = $flat($item);
            }
            return implode(', ', array_filter($parts, 'strlen'));
        }
        if (is_object($v)) {
            return isset($v->name) ? (string) $v->name : '';
        }
        return html_entity_decode((string) $v, ENT_QUOTES, 'UTF-8');
    };
    $field = function ($key, $id) use ($flat) {
        return $flat(get_field($key, $id));
    };

    $photo_ids = get_posts([
        'post_type'      => 'photo',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    foreach ($photo_ids as $post_id) {
        $location = '';
        $terms = get_the_terms($post_id, 'topothesia');
        if ($terms && !is_wp_error($terms)) {
            $location = implode(', ', wp_list_pluck($terms, 'name'));
        }

        fputcsv($output, [
            $post_id,
            html_entity_decode(get_the_title($post_id), ENT_QUOTES, 'UTF-8'),
            get_permalink($post_id),
            $field('perigrafi', $post_id),
            $location,
            $field('xronologia', $post_id),
            $field('eikonizomena_prosopa', $post_id),
            $field('thematiki_katigoria', $post_id),
            $field('pigi__idioktitis', $post_id),
            $field('typos_photografias', $post_id),
            $field('katastasi_tekmiriosis', $post_id),
            $field('dikaiomata_adeia_xrisis', $post_id),
        ]);
    }

    fclose($output);
    exit;
});
