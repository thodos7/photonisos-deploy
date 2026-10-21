<?php
/**
 * Photonisos - Αναζήτηση φωτογραφιών
 *
 * Shortcode [photo_search]. Τοποθεσία με ID όρου, φίλτρο χρονολογίας μόνο όταν στενέψει ο slider.
 * Στο πρόσθετο Code Snippets επικολλάται ο κώδικας ΧΩΡΙΣ τη γραμμή <?php.
 */

/**
 * Shortcode: [photo_search]
 * Αναζήτηση φωτογραφιών (CPT: photo) με:
 * - Θεματική (ACF select field: thematiki_katigoria)
 * - Τοποθεσία (taxonomy: topothesia)
 * - Slider χρονολογίας 1900–2026 (ACF numeric field: xronologia)
 *
 * ΣΗΜΕΙΩΣΗ: thematiki_katigoria ΔΕΝ είναι taxonomy. Είναι ACF select.
 */

function photonisos_photo_search_shortcode() {

    // ---------------- GET filters ----------------
    $keyword   = isset($_GET['ps_q']) ? sanitize_text_field($_GET['ps_q']) : '';
    // Η τοποθεσία περνά ως αριθμός (ID του όρου), όχι ως slug: τα ελληνικά slug αλλοιώνονται
    $location  = (isset($_GET['ps_loc']) && !is_array($_GET['ps_loc'])) ? absint($_GET['ps_loc']) : 0;
    $theme     = isset($_GET['ps_theme']) ? sanitize_text_field($_GET['ps_theme']) : '';
    $paged     = get_query_var('paged') ? (int) get_query_var('paged') : 1;

    $year_from = isset($_GET['ps_year_from']) ? (int) $_GET['ps_year_from'] : 1900;
    $year_to   = isset($_GET['ps_year_to'])   ? (int) $_GET['ps_year_to']   : 2026;

    if ($year_from > $year_to) {
        $tmp = $year_from;
        $year_from = $year_to;
        $year_to = $tmp;
    }

    // ---------------- Τοποθεσίες (taxonomy) ----------------
    $locations = array();
    if (taxonomy_exists('topothesia')) {
        $locations = get_terms(array(
            'taxonomy'   => 'topothesia',
            'hide_empty' => false,
        ));
    }

    // ---------------- Θεματικές (ACF select choices) ----------------
    // Παίρνουμε τα choices του ACF field "thematiki_katigoria"
    $theme_choices = array();

    if (function_exists('acf_get_field')) {
        $field_obj = acf_get_field('thematiki_katigoria'); 
        // Αν δεν το βρει έτσι, θα πέσει κάτω σε fallback.
        if (is_array($field_obj) && !empty($field_obj['choices']) && is_array($field_obj['choices'])) {
            $theme_choices = $field_obj['choices']; // value => label
        }
    }

    // Fallback αν δεν βρεθεί με acf_get_field (σε μερικά setups θέλει field key)
    // Τότε θα δείξει απλά input αντί για dropdown.
    $has_theme_dropdown = !empty($theme_choices);

    ob_start();
    ?>

    <div class="photonisos-search-box">
        <form method="get" class="photonisos-search-form">
            <div class="ps-row">

                <!-- ΘΕΜΑΤΙΚΗ (ACF SELECT) -->
                <div class="ps-field">
                    <label>Θεματική</label>

                    <?php if ($has_theme_dropdown): ?>
                        <select name="ps_theme">
                            <option value="">Όλες οι θεματικές</option>
                            <?php foreach ($theme_choices as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($theme, (string)$value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <!-- Αν δεν βρούμε choices, δίνουμε προσωρινά text input για να μη σπάει -->
                        <input type="text" name="ps_theme" value="<?php echo esc_attr($theme); ?>" placeholder="γράψε θεματική (ACF)">
                    <?php endif; ?>
                </div>

                <!-- ΤΟΠΟΘΕΣΙΑ (taxonomy) -->
                <?php if (!empty($locations) && !is_wp_error($locations)): ?>
                    <div class="ps-field">
                        <label>Τοποθεσία</label>
                        <select name="ps_loc">
                            <option value="">Όλες οι τοποθεσίες</option>
                            <?php foreach ($locations as $term): ?>
                                <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($location, (int) $term->term_id); ?>>
                                    <?php echo esc_html($term->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- ΧΡΟΝΟΛΟΓΙΑ -->
                <div class="ps-field ps-filter-years">
                    <label>Χρονολογία</label>

                    <div class="ps-year-slider">
                   <input type="range" id="ps_year_from_range" min="1900" max="2026" value="<?php echo esc_attr($year_from); ?>" aria-label="Χρονολογία από">
<input type="range" id="ps_year_to_range"   min="1900" max="2026" value="<?php echo esc_attr($year_to); ?>" aria-label="Χρονολογία έως">
                        <div class="ps-year-slider-track"></div>
                    </div>

                    <div class="ps-year-values">
                        <span id="ps_year_from_label"><?php echo esc_html($year_from); ?></span>
                        <span>–</span>
                        <span id="ps_year_to_label"><?php echo esc_html($year_to); ?></span>
                    </div>

                    <input type="hidden" id="ps_year_from" name="ps_year_from" value="<?php echo esc_attr($year_from); ?>">
                    <input type="hidden" id="ps_year_to"   name="ps_year_to"   value="<?php echo esc_attr($year_to); ?>">
                </div>

                <!-- ΛΕΞΗ-ΚΛΕΙΔΙ (αν τη θες, κράτα την. Αν δεν τη θες, σβήσε αυτό το block) -->
                <div class="ps-field ps-field-text">
                    <label>Αναζήτηση</label>
                    <input type="text" name="ps_q" value="<?php echo esc_attr($keyword); ?>" placeholder="π.χ. λιμάνι, γάμος…">
                </div>

                <div class="ps-field ps-field-button">
                    <button type="submit">Αναζήτηση</button>
                </div>

            </div>
        </form>
    </div>

    <?php
    // ---------------- WP_Query ----------------
    $args = array(
        'post_type'      => array('photo'), // ΕΔΩ ΣΩΣΤΟ
        'post_status'    => 'publish',
        'posts_per_page' => 24,
        'paged'          => $paged,
    );

    // keyword
    if (!empty($keyword)) {
        $args['s'] = $keyword;
    }

    // tax_query (τοποθεσία) - με ID όρου
    if (!empty($location) && taxonomy_exists('topothesia')) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'topothesia',
                'field'    => 'term_id',
                'terms'    => (int) $location,
            )
        );
    }

    // meta_query
    $meta_query = array();

    // χρονoλογία (ACF numeric field xronologia) - μόνο αν στένεψε ο slider
    if ( $year_from > 1900 || $year_to < 2026 ) {
        $meta_query[] = array(
            'key'     => 'xronologia',
            'value'   => array($year_from, $year_to),
            'compare' => 'BETWEEN',
            'type'    => 'NUMERIC',
        );
    }

    // θεματική (ACF select field thematiki_katigoria)
    if (!empty($theme)) {
        $meta_query[] = array(
            'key'     => 'thematiki_katigoria',
            'value'   => $theme,
            'compare' => '=',
        );
    }

    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    $q = new WP_Query($args);
    ?>

    <div class="photonisos-results">
        <?php if ($q->have_posts()): ?>
            <div class="ps-grid">
                <?php while ($q->have_posts()): $q->the_post(); ?>
                    <article class="ps-card">
                        <a href="<?php the_permalink(); ?>" class="ps-thumb-link">
                            <div class="ps-thumb">
                                <?php
                                if (has_post_thumbnail()) {
                                    the_post_thumbnail('medium_large');
                                } else {
                                    echo '<div class="ps-no-thumb">Χωρίς εικόνα</div>';
                                }
                                ?>
                            </div>
                        </a>

                        <div class="ps-card-body">
                            <h3 class="ps-title"><?php the_title(); ?></h3>

                            <div class="ps-meta">
                                <?php
                                // Τοποθεσία
                                if (taxonomy_exists('topothesia')) {
                                    $terms = get_the_terms(get_the_ID(), 'topothesia');
                                    if ($terms && !is_wp_error($terms)) {
                                        echo '<span class="ps-location">' . esc_html($terms[0]->name) . '</span>';
                                    }
                                }

                                // Χρονολογία
                                $year = get_post_meta(get_the_ID(), 'xronologia', true);
                                if (!empty($year)) {
                                    echo '<span class="ps-year">' . esc_html($year) . '</span>';
                                }

                                // Θεματική (label)
                                $theme_val = get_post_meta(get_the_ID(), 'thematiki_katigoria', true);
                                if (!empty($theme_val) && !empty($theme_choices) && isset($theme_choices[$theme_val])) {
                                    echo '<span class="ps-theme">' . esc_html($theme_choices[$theme_val]) . '</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            echo paginate_links(array(
                'current' => max(1, $paged),
                'total'   => $q->max_num_pages,
                'type'    => 'list',
            ));
            ?>

        <?php else: ?>
            <p class="ps-empty">Δεν βρέθηκαν φωτογραφίες.</p>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </div>

 <script>
document.addEventListener('DOMContentLoaded', function () {
    var fromRange  = document.getElementById('ps_year_from_range');
    var toRange    = document.getElementById('ps_year_to_range');
    var fromLabel  = document.getElementById('ps_year_from_label');
    var toLabel    = document.getElementById('ps_year_to_label');
    var fromHidden = document.getElementById('ps_year_from');
    var toHidden   = document.getElementById('ps_year_to');
    var track      = document.querySelector('.ps-year-slider-track');

    if (!fromRange || !toRange) return;

    var min = parseInt(fromRange.min, 10);
    var max = parseInt(fromRange.max, 10);

    function syncYears(changed) {
        var fromVal = parseInt(fromRange.value, 10);
        var toVal   = parseInt(toRange.value, 10);

        if (fromVal > toVal) {
            if (changed === 'from') {
                toVal = fromVal;
                toRange.value = toVal;
            } else {
                fromVal = toVal;
                fromRange.value = fromVal;
            }
        }

        fromLabel.textContent = fromVal;
        toLabel.textContent   = toVal;
        fromHidden.value = fromVal;
        toHidden.value   = toVal;

        if (track) {
            var leftPct  = ((fromVal - min) / (max - min)) * 100;
            var rightPct = ((toVal   - min) / (max - min)) * 100;
            track.style.left  = leftPct + '%';
            track.style.width = (rightPct - leftPct) + '%';
        }
    }

    fromRange.addEventListener('input', function () { syncYears('from'); });
    toRange.addEventListener('input', function () { syncYears('to'); });
    syncYears();
});
</script>

    <?php
    return ob_get_clean();
}
add_shortcode('photo_search', 'photonisos_photo_search_shortcode');
// Ποτέ cache σε αναζήτηση με ενεργό φίλτρο — έτσι νέες φωτογραφίες
// εμφανίζονται αμέσως, χωρίς να χρειάζεται χειροκίνητο purge κάθε φορά
if ( isset($_GET['ps_q'], $_GET['ps_theme'], $_GET['ps_loc'], $_GET['ps_year_from']) ) {
    if ( function_exists('do_action') ) {
        do_action('litespeed_control_set_nocache', 'photonisos active search filter');
    }
}
