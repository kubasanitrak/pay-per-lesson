<?php
/**
 * Template Name: PPL My Lessons
 * Page: /dashboard/
 */
if (!defined('ABSPATH')) exit;

get_header();

$user_id = get_current_user_id();
$purchased = get_user_meta($user_id, '_ppl_purchased_lessons', true) ?: [];

?>
<div class="ppl-my-lessons" style="max-width:900px;margin:40px auto;padding:0 20px;">
    <h1>My Lessons</h1>

    <?php if (empty($purchased)) : ?>
        <div style="text-align:center;padding:60px;background:#f9f9f9;border-radius:12px;">
            <p style="font-size:18px;color:#666;">You haven't purchased any lessons yet.</p>
            <a href="<?php echo home_url(); ?>" class="ppl-add-to-basket" style="display:inline-block;margin-top:20px;">Browse Lessons</a>
        </div>
    <?php else : ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:25px;">
            <?php foreach ($purchased as $lesson_id) :
                $post = get_post($lesson_id);
                if (!$post || $post->post_status !== 'publish') continue;
                $thumb = get_the_post_thumbnail_url($lesson_id, 'medium');
            ?>
                <div class="lesson-card">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($post->post_title); ?>" style="width:100%;height:180px;object-fit:cover;border-radius:8px;">
                    <?php endif; ?>
                    
                    <div style="padding:15px 5px;flex:1;">
                        <h3 style="margin:0 0 10px 0;font-size:20px;"><?php echo esc_html($post->post_title); ?></h3>
                        <a href="<?php echo get_permalink($lesson_id); ?>" 
                           class="ppl-add-to-basket" 
                           style="display:inline-block;padding:12px 24px;font-size:15px;">
                            Open Lesson →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php
get_footer();