<?php
/**
 * Blocksy functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Blocksy
 */

if (version_compare(PHP_VERSION, '5.7.0', '<')) {
	require get_template_directory() . '/inc/php-fallback.php';
	return;
}

require get_template_directory() . '/inc/init.php';

// Enqueue Shopzzy custom styles and flexbox rules
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('shopzzy-custom', get_template_directory_uri() . '/shopzzy.css', array(), '1.0.0');
});

/**
 * Display products with active discount coupons in WooCommerce only
 */
function display_custom_sale_products_widget_output() {
    // 1. Fetch active published WooCommerce discount coupons
    $all_coupons = get_posts( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
    ) );

    $coupon_product_ids = array();
    $coupons_by_product = array();

    foreach ( $all_coupons as $c ) {
        $cp = new WC_Coupon( $c->ID );

        // Ignore expired coupons
        if ( $cp->get_date_expires() && $cp->get_date_expires()->getTimestamp() < time() ) {
            continue;
        }

        $code           = strtoupper( $cp->get_code() );
        $amount         = $cp->get_amount();
        $type           = $cp->get_discount_type();
        $discount_label = ( $type === 'percent' ) ? ( $amount . '% OFF' ) : ( '₹' . number_format( $amount, 0 ) . ' OFF' );
        $pids           = $cp->get_product_ids();

        if ( ! empty( $pids ) ) {
            foreach ( $pids as $pid ) {
                $pid = (int) $pid;
                $coupon_product_ids[] = $pid;
                $coupons_by_product[$pid][] = array(
                    'code'           => $code,
                    'discount_label' => $discount_label,
                    'amount'         => $amount,
                    'type'           => $type,
                );
            }
        }
    }

    // Filter down to unique products that have active coupons ONLY
    $coupon_product_ids = array_values( array_unique( array_filter( $coupon_product_ids ) ) );

    if ( empty( $coupon_product_ids ) ) {
        echo '<p>No products currently have active discount coupons.</p>';
        return;
    }

    // 2. Query only products with active discount coupons
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'post__in'       => $coupon_product_ids,
        'posts_per_page' => count( $coupon_product_ids ),
        'orderby'        => 'post__in',
    );

    $sale_query = new WP_Query( $args );

    if ( $sale_query->have_posts() ) {
        echo '<div class="product-grid-flex" style="margin-top: var(--spacing-lg);">';
        while ( $sale_query->have_posts() ) {
            $sale_query->the_post();
            global $product;
            $pid = get_the_ID();

            // Strict check: only display if this product has active coupons mapped
            if ( empty( $coupons_by_product[$pid] ) ) {
                continue;
            }

            $permalink     = esc_url( get_permalink() );
            $title         = esc_html( get_the_title() );
            $thumbnail_url = wp_get_attachment_image_url( $product->get_image_id(), 'medium' ) ?: get_the_post_thumbnail_url( $pid, 'medium' );
            $c_info        = $coupons_by_product[$pid][0];
            $pct_text      = ( $c_info['type'] === 'percent' ) ? ( $c_info['amount'] . '% OFF' ) : ( $c_info['discount_label'] );
            $coupon_badge  = '<span class="badge-discount">' . esc_html( $pct_text ) . '</span>';

            $terms    = wp_get_post_terms( $pid, 'product_cat', array( 'fields' => 'names' ) );
            $cat_name = ! empty( $terms ) ? esc_html( $terms[0] ) : 'Special Deal';

            echo '<article class="card-product">';
            echo '<div class="card-product-media">';
            echo '<a href="' . $permalink . '"><img src="' . esc_url( $thumbnail_url ) . '" alt="' . $title . '" class="card-product-image" /></a>';
            echo '</div>';
            echo '<div class="card-product-body">';
            echo '<div class="card-product-meta" style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">';
            echo '<span class="product-category-name" style="font-size:0.8rem;text-transform:uppercase;color:#888;">' . $cat_name . '</span>';
            echo $coupon_badge;
            echo '</div>';
            echo '<h3 class="product-name" style="font-size:1.15rem;margin:6px 0;"><a href="' . $permalink . '" style="color:inherit;text-decoration:none;">' . $title . '</a></h3>';
            echo '<div class="product-pricing" style="margin:10px 0;"><span class="price">' . $product->get_price_html() . '</span></div>';
            echo '</div>';
            echo '<div class="card-product-actions" style="margin-top:auto;padding:12px 16px 16px;">';
            echo '<a href="' . $permalink . '" class="button-deal" style="display:block;text-align:center;text-decoration:none;padding:10px;border-radius:6px;font-weight:600;background:var(--color-primary, #000);color:#fff;">Claim Deal</a>';
            echo '</div>';
            echo '</article>';
        }
        echo '</div>';
    }
    wp_reset_postdata();
}

add_shortcode( 'custom_sale_products_widget', function() {
    ob_start();
    display_custom_sale_products_widget_output();
    return ob_get_clean();
});

// Auto-apply coupon on dropdown selection and handle dynamic refresh in cart
add_action( 'wp_footer', function() {
    if ( is_cart() ) {
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            function bindCouponDropdown() {
                var $select = $('#coupon_code.ct-coupon-select');
                if (!$select.length) return;

                $select.off('change.autoapply').on('change.autoapply', function() {
                    var val = $(this).val();
                    if (val) {
                        var $selectedOpt = $(this).find('option:selected');
                        if ($selectedOpt.data('valid') == '1' || !$selectedOpt.is(':disabled')) {
                            // Automatically submit coupon application form
                            var $btn = $('#apply_coupon_btn');
                            if ($btn.length) {
                                $btn.trigger('click');
                            } else {
                                $(this).closest('form').find('button[name="apply_coupon"]').trigger('click');
                            }
                        }
                    }
                });
            }

            $(document).ready(bindCouponDropdown);
            $(document.body).on('updated_wc_div updated_cart_totals', bindCouponDropdown);
        });
        </script>
        <style>
        .ct-coupon-select option:disabled {
            color: #94a3b8 !important;
            background-color: #f8fafc !important;
        }
        .ct-coupon-select option:not(:disabled) {
            color: #0f172a !important;
            font-weight: 600;
        }
        </style>
        <?php
    }
});

