<?php
/**
 * Photonisos - Τρία επίπεδα χρηστών
 *
 * Ρόλος documentalist («Τεκμηριωτής»), δικαιώματα photo/photos, περιορισμένο μενού.
 * Στο πρόσθετο Code Snippets επικολλάται ο κώδικας ΧΩΡΙΣ τη γραμμή <?php.
 */

/**
 * Photonisos — 3 επίπεδα χρηστών
 * 1) Επισκέπτης: χωρίς λογαριασμό, μόνο ανάγνωση/αναζήτηση (δεν αλλάζει τίποτα εδώ)
 * 2) Τεκμηριωτής (ρόλος 'documentalist'): μόνο εισαγωγή/επεξεργασία φωτογραφιών
 * 3) Administrator: όλα τα υπόλοιπα
 */

// Το CPT "photo" αποκτά ΔΙΚΑ ΤΟΥ δικαιώματα (photo/photos) αντί να μοιράζεται
// τα γενικά edit_posts/publish_posts των άρθρων.
add_filter('register_post_type_args', function ($args, $post_type) {
    if ($post_type === 'photo') {
        $args['capability_type'] = array('photo', 'photos');
        $args['map_meta_cap']    = true;
    }
    return $args;
}, 20, 2);

// Η τοποθεσία: όποιος επεξεργάζεται φωτογραφίες μπορεί να επιλέξει υπάρχον χωριό,
// αλλά μόνο ο admin προσθέτει/σβήνει χωριά.
add_filter('register_taxonomy_args', function ($args, $taxonomy) {
    if ($taxonomy === 'topothesia') {
        $args['capabilities'] = array(
            'manage_terms' => 'manage_options',
            'edit_terms'   => 'manage_options',
            'delete_terms' => 'manage_options',
            'assign_terms' => 'edit_photos',
        );
    }
    return $args;
}, 20, 2);

add_action('init', function () {
    if (get_option('photonisos_roles_v1_done')) {
        return;
    }

    // Ο Administrator πρέπει να πάρει ρητά τα νέα photo capabilities —
    // η αλλαγή του capability_type δεν τα δίνει αυτόματα σε κανέναν, ούτε στον admin.
    $admin = get_role('administrator');
    if ($admin) {
        foreach ([
            'edit_photo', 'read_photo', 'delete_photo',
            'edit_photos', 'edit_others_photos', 'edit_private_photos', 'edit_published_photos',
            'publish_photos', 'read_private_photos',
            'delete_photos', 'delete_private_photos', 'delete_published_photos', 'delete_others_photos',
        ] as $cap) {
            $admin->add_cap($cap);
        }
    }

    // Καθαρός ρόλος Τεκμηριωτή: μόνο ό,τι χρειάζεται για φωτογραφίες.
    remove_role('documentalist');
    add_role('documentalist', 'Τεκμηριωτής', [
        'read'                    => true,
        'upload_files'            => true,
        'edit_photos'             => true,
        'edit_published_photos'   => true,
        'edit_others_photos'      => true,
        'publish_photos'          => true,
        'delete_photos'           => true,
        'delete_published_photos' => true,
    ]);

    update_option('photonisos_roles_v1_done', true);
});

// Διόρθωση του παλιού λάθους: έλεγχε 'tekmiriotis' που δεν υπάρχει· ο πραγματικός
// ρόλος λέγεται 'documentalist'.
add_action('admin_menu', function () {
    if (in_array('documentalist', (array) wp_get_current_user()->roles, true)) {
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('users.php');
        remove_menu_page('ct-dashboard');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('edit.php?post_type=acf-field-group');
        remove_menu_page('snippet');
    }
}, 999);
