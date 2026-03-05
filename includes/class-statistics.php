<?php
/**
 * Pay Per Lesson - Admin Statistics
 * Full admin stats page as specified:
 * - Overview (total revenue, orders, average)
 * - By Time (monthly breakdown)
 * - By Bundle (discount tiers)
 * - By Lesson (top selling lessons with sales & revenue)
 *
 * Uses the ppl_orders table + joins with lessons CPT
 * Reads currency from ppl_settings
 * Simple, clean, no external dependencies
 */

if (!defined('ABSPATH')) exit;

class PPL_Statistics {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'pay-per-lesson',
            'Statistics',
            'Statistics',
            'manage_options',
            'pay-per-lesson-stats',
            [$this, 'render_stats_page']
        );
    }

    private function get_settings() {
        return wp_parse_args(
            get_option('ppl_settings', []),
            [
                'currency_code'   => 'CZK',
                'currency_symbol' => 'Kč',
                'currency_position' => 'after'
            ]
        );
    }

    private function format_price($amount) {
        $s = $this->get_settings();
        $price = number_format($amount, 2);
        return $s['currency_position'] === 'before'
            ? $s['currency_symbol'] . $price
            : $price . ' ' . $s['currency_symbol'];
    }

    public function render_stats_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ppl_orders';

        // === OVERVIEW ===
        $total_orders = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $completed = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'completed'");
        $pending   = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'");
        $revenue   = $wpdb->get_var("SELECT SUM(total) FROM $table WHERE status = 'completed'") ?: 0;

        // Average order value
        $avg_order = $completed > 0 ? $revenue / $completed : 0;

        // === BY TIME (last 12 months) ===
        $monthly = $wpdb->get_results("
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                   COUNT(*) as orders,
                   SUM(total) as revenue
            FROM $table
            WHERE status = 'completed'
            GROUP BY month
            ORDER BY month DESC
            LIMIT 12
        ");

        // === BY BUNDLE ===
        $bundle_data = $wpdb->get_results("
            SELECT lesson_ids FROM $table WHERE status = 'completed'
        ");
        $bundle_counts = ['single' => 0, '2_4' => 0, '5_9' => 0, '10plus' => 0];
        foreach ($bundle_data as $row) {
            $ids = json_decode($row->lesson_ids, true) ?: [];
            $cnt = count($ids);
            if ($cnt === 1) $bundle_counts['single']++;
            elseif ($cnt <= 4) $bundle_counts['2_4']++;
            elseif ($cnt <= 9) $bundle_counts['5_9']++;
            else $bundle_counts['10plus']++;
        }

        // === TOP LESSONS ===
        $lesson_sales = [];
        foreach ($bundle_data as $row) {
            $ids = json_decode($row->lesson_ids, true) ?: [];
            foreach ($ids as $lesson_id) {
                if (!isset($lesson_sales[$lesson_id])) {
                    $lesson_sales[$lesson_id] = ['sales' => 0, 'revenue' => 0, 'title' => get_the_title($lesson_id)];
                }
                $lesson_sales[$lesson_id]['sales']++;
                // Approximate revenue split (simple average per lesson in order)
                $order_total = $wpdb->get_var($wpdb->prepare(
                    "SELECT total FROM $table WHERE lesson_ids LIKE %s AND status = 'completed' LIMIT 1",
                    '%"'.$lesson_id.'"%'
                )) ?: 0;
                $lesson_sales[$lesson_id]['revenue'] += $order_total / max(1, count($ids));
            }
        }
        uasort($lesson_sales, function($a, $b) {
            return $b['sales'] <=> $a['sales'];
        });
        $top_lessons = array_slice($lesson_sales, 0, 10);
        ?>
        <div class="wrap">
            <h1>Pay Per Lesson — Statistics</h1>

            <!-- OVERVIEW -->
            <div class="postbox" style="padding:20px;margin:20px 0;background:#fff;">
                <h2>Overview</h2>
                <table class="widefat" style="margin-top:15px;">
                    <tr><th>Total Orders</th><td><strong><?php echo number_format($total_orders); ?></strong></td></tr>
                    <tr><th>Completed</th><td><strong style="color:green"><?php echo number_format($completed); ?></strong></td></tr>
                    <tr><th>Pending</th><td><strong style="color:orange"><?php echo number_format($pending); ?></strong></td></tr>
                    <tr><th>Total Revenue</th><td><strong><?php echo $this->format_price($revenue); ?></strong></td></tr>
                    <tr><th>Average Order Value</th><td><strong><?php echo $this->format_price($avg_order); ?></strong></td></tr>
                </table>
            </div>

            <!-- BY TIME -->
            <div class="postbox" style="padding:20px;margin:20px 0;background:#fff;">
                <h2>Revenue by Month (last 12 months)</h2>
                <?php if ($monthly) : ?>
                <table class="widefat striped">
                    <thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($monthly as $m) : ?>
                        <tr>
                            <td><?php echo esc_html($m->month); ?></td>
                            <td><?php echo number_format($m->orders); ?></td>
                            <td><strong><?php echo $this->format_price($m->revenue); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p>No completed orders yet.</p>
                <?php endif; ?>
            </div>

            <!-- BY BUNDLE -->
            <div class="postbox" style="padding:20px;margin:20px 0;background:#fff;">
                <h2>Purchases by Bundle Size</h2>
                <table class="widefat striped">
                    <thead><tr><th>Bundle Size</th><th>Number of Orders</th><th>Percentage</th></tr></thead>
                    <tbody>
                        <tr><td>1 lesson</td><td><?php echo $bundle_counts['single']; ?></td><td><?php echo $total_orders ? round($bundle_counts['single']/$total_orders*100,1) : 0; ?>%</td></tr>
                        <tr><td>2–4 lessons</td><td><?php echo $bundle_counts['2_4']; ?></td><td><?php echo $total_orders ? round($bundle_counts['2_4']/$total_orders*100,1) : 0; ?>%</td></tr>
                        <tr><td>5–9 lessons (15% discount)</td><td><?php echo $bundle_counts['5_9']; ?></td><td><?php echo $total_orders ? round($bundle_counts['5_9']/$total_orders*100,1) : 0; ?>%</td></tr>
                        <tr><td>10+ lessons (30% discount)</td><td><?php echo $bundle_counts['10plus']; ?></td><td><?php echo $total_orders ? round($bundle_counts['10plus']/$total_orders*100,1) : 0; ?>%</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- TOP LESSONS -->
            <div class="postbox" style="padding:20px;margin:20px 0;background:#fff;">
                <h2>Top Selling Lessons</h2>
                <?php if ($top_lessons) : ?>
                <table class="widefat striped">
                    <thead><tr><th>Lesson</th><th>Sales</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_lessons as $data) : ?>
                        <tr>
                            <td><a href="<?php echo get_edit_post_link($data['title'] ? array_search($data['title'], array_column($top_lessons,'title')) : ''); ?>" target="_blank"><?php echo esc_html($data['title'] ?: 'Lesson #'.key($lesson_sales)); ?></a></td>
                            <td><strong><?php echo $data['sales']; ?></strong></td>
                            <td><strong><?php echo $this->format_price($data['revenue']); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p>No sales yet.</p>
                <?php endif; ?>
            </div>

            <p style="text-align:right; color:#666; font-size:12px;">
                Last updated: <?php echo current_time('mysql'); ?> • Data from <code><?php echo $table; ?></code>
            </p>
        </div>
        <?php
    }
}