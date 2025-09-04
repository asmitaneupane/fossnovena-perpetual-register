<?php
if ( ! defined('ABSPATH') ) exit;

class FNPR_Frontend {

    public function hooks() {
        add_shortcode('perpetual_register', [$this, 'shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
    }

    public function assets() {
        // No external CSS required, but add minimal styles if you want:
        $css = "
        .fnpr-search{margin:1rem 0}
        .fnpr-table{width:100%;border-collapse:collapse}
        .fnpr-table th,.fnpr-table td{padding:.5rem;border-bottom:1px solid #e5e7eb}
        .fnpr-pagination a{margin:0 .25rem;text-decoration:none}
        .fnpr-group{background:#f9fafb;padding:.25rem .5rem;font-weight:600}
        ";
        wp_add_inline_style('wp-block-library', $css);
    }

    public function shortcode($atts = []) {
        global $wpdb;
        $a = shortcode_atts([
            'per_page' => 100,
            'sort'     => 'asc',  // asc|desc
            'search'   => 'true', // true|false
            'show_download' => 'false' // true shows CSV download if logged in as admin
        ], $atts, 'perpetual_register');

        $per_page = max(1, (int)$a['per_page']);
        $sort = strtolower($a['sort']) === 'desc' ? 'DESC' : 'ASC';
        $page = max(1, (int)($_GET['pr_page'] ?? 1));
        $search = trim(sanitize_text_field($_GET['pr_search'] ?? ''));
        $offset = ($page - 1) * $per_page;

        $table = FNPR_TABLE;

        $where = 'WHERE 1=1';
        $params = [];
        if ($search !== '') {
            $where .= " AND full_name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

       if (!empty($params)) {
            $total = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $table $where", ...$params) );
            $names = $wpdb->get_col( $wpdb->prepare("SELECT full_name FROM $table $where ORDER BY full_name $sort LIMIT %d OFFSET %d", ...array_merge($params, [$per_page, $offset])) );
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
            $names = $wpdb->get_col("SELECT full_name FROM $table $where ORDER BY full_name $sort LIMIT $per_page OFFSET $offset");
        }
        ob_start();
        ?>
        <div class="fnpr-wrap">
            <?php if (filter_var($a['search'], FILTER_VALIDATE_BOOLEAN)): ?>
                <form class="fnpr-search" method="get">
                    <?php
                    // preserve other query vars
                    foreach ($_GET as $k=>$v) {
                        if (in_array($k, ['pr_search','pr_page'], true)) continue;
                        echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">';
                    }
                    ?>
                    <input type="text" name="pr_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search names…','fossnovena-pr'); ?>">
                    <button type="submit"><?php esc_html_e('Search','fossnovena-pr'); ?></button>
                </form>
            <?php endif; ?>

            <?php if (current_user_can('manage_options') && filter_var($a['show_download'], FILTER_VALIDATE_BOOLEAN)): ?>
                <p><a href="<?php echo esc_url(FNPR_UPLOAD_URL . '/perpetual-register.csv'); ?>" target="_blank"><?php esc_html_e('Download Master CSV','fossnovena-pr'); ?></a></p>
            <?php endif; ?>

            <?php
            if (empty($names)) {
                echo '<p>'.esc_html__('No entries found.','fossnovena-pr').'</p>';
            } else {
                // Group by first letter
                $current = '';
                echo '<table class="fnpr-table"><tbody>';
                foreach ($names as $n) {
                    $first = mb_strtoupper(mb_substr($n, 0, 1));
                    if ($first !== $current) {
                        $current = $first;
                        echo '<tr><td colspan="1" class="fnpr-group">'.esc_html($current).'</td></tr>';
                    }
                    echo '<tr><td>'.esc_html($n).'</td></tr>';
                }
                echo '</tbody></table>';
            }

            // Pagination
            $pages = (int) ceil($total / $per_page);
            if ($pages > 1) {
                echo '<div class="fnpr-pagination">';
                for ($p = 1; $p <= $pages; $p++) {
                    $qs = $_GET;
                    $qs['pr_page'] = $p;
                    $url = esc_url(add_query_arg($qs));
                    if ($p === $page) {
                        echo '<strong>'.$p.'</strong> ';
                    } else {
                        echo '<a href="'.$url.'">'.$p.'</a> ';
                    }
                }
                echo '</div>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
