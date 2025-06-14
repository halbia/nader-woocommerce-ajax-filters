<?php
defined('ABSPATH') || exit;

/*
Plugin Name: Nader Woocommerce Ajax Filters
Version: 1.0
Author: Ali Emadzadeh
Text Domain: nader
*/

class Nader_Woocommerce_Ajax_Filters{
    private $shop_slug;
    private $filter_types = [
        'page'      => [
            'type' => 'page',
        ],
        'orderby'   => [
            'type' => 'orderby',
        ],
        'category'  => [
            'type'     => 'taxonomy',
            'taxonomy' => 'product_cat'
        ],
        'brand'     => [
            'type'     => 'taxonomy',
            'taxonomy' => 'product_brand'
        ],
        'attribute' => [
            'type' => 'attribute'
        ],
        'rating'    => [
            'type'       => 'meta',
            'key'        => '_wc_average_rating',
            'compare'    => '>=',
            'value_type' => 'decimal'
        ],
        'stock'     => [
            'type'       => 'meta',
            'key'        => '_stock_status',
            'compare'    => 'IN',
            'value_type' => 'string'
        ],
        'price'     => [
            'type'       => 'meta',
            'key'        => '_price',
            'compare'    => 'BETWEEN',
            'value_type' => 'numeric'
        ]
    ];

    private $allowed_compares = [
        '=',
        '!=',
        '>',
        '>=',
        '<',
        '<=',
        'LIKE',
        'NOT LIKE',
        'IN',
        'NOT IN',
        'BETWEEN',
        'NOT BETWEEN'
    ];

    private $allowed_types = ['NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'];


    public function __construct()
    {
        $this->shop_slug = get_post_field('post_name', get_option('woocommerce_shop_page_id'));

        add_action('init', [$this, 'init_rewrites']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('parse_request', [$this, 'parse_request']);
        add_action('pre_get_posts', [$this, 'pre_get_posts']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_nader_woocommerce_ajax_filters', [$this, 'ajax_handler']);
        add_action('wp_ajax_nopriv_nader_woocommerce_ajax_filters', [$this, 'ajax_handler']);

        add_action('wp_ajax_get_product_price_range', [$this, 'get_product_price_range']);
        add_action('wp_ajax_nopriv_get_product_price_range', [$this, 'get_product_price_range']);

        add_shortcode('nader_woocommerce_ajax_filters', [$this, 'shortcode_handler']);
    }

    public function init_rewrites()
    {
        // Rewrite rule برای صفحه فروشگاه
        add_rewrite_rule(
            "^({$this->shop_slug})/([^/]+(/[^/]+)*)/?$",
            'index.php?post_type=product&wc_ajax_filters=$matches[2]',
            'top'
        );

        // Rewrite rule جدید برای دسته‌بندی‌ها
        add_rewrite_rule(
            "^product-category/([^/]+)/([^/]+(/[^/]+)*)/?$",
            'index.php?product_cat=$matches[1]&wc_ajax_filters=$matches[2]',
            'top'
        );

        // Rewrite rule جدید برای فیلترهای تکی
        add_rewrite_rule(
            "^({$this->shop_slug})/([^/]+)/([^/]+)/?$",
            'index.php?post_type=product&wc_ajax_filters=$matches[2]/$matches[3]',
            'top'
        );

        add_rewrite_tag('%wc_ajax_filters%', '([^&]+)');
    }

    public function add_query_vars($vars)
    {
        $vars[] = 'wc_ajax_filters';
        return $vars;
    }

    private function map_stock_values($value)
    {
        $mapping = [
            'in-stock'     => 'instock',
            'out-of-stock' => 'outofstock',
            'on-backorder' => 'onbackorder'
        ];
        return str_replace(array_keys($mapping), array_values($mapping), $value);
    }

    private function parse_filters($url)
    {
        $url = $this->map_stock_values($url);
        $segments = explode('/', trim($url, '/'));

        $field_types = [
            'price' => [
                'type'       => 'numeric_array',
                'meta_key'   => '_price',
                'compare'    => 'BETWEEN',
                'value_type' => 'NUMERIC' // اضافه کردن این خط
            ],
            'rating'   => ['type' => 'numeric', 'meta_key' => '_wc_average_rating', 'compare' => '>='],
            'page'     => ['type' => 'numeric'],
            'orderby'  => ['type' => 'string'],
            'category' => ['type' => 'term_array', 'taxonomy' => 'product_cat'],
            'brand'    => ['type' => 'term_array', 'taxonomy' => 'product_brand'],
            'stock'    => ['type' => 'term_array', 'meta_key' => '_stock_status']
        ];

        for ($i = 0; $i < count($segments); $i += 2) {
            if (!isset($segments[$i + 1]))
                continue;

            $key = sanitize_key($segments[$i]); // اعتبارسنجی کلید
            $value = $segments[$i + 1];

            if (isset($field_types[$key])) {
                $config = $field_types[$key];
                switch ($config['type']) {
                    case 'numeric':
                        $filters[$key] = absint($value);
                        if (isset($config['meta_key'])) {
                            $filters['meta_query'][] = [
                                'key'     => $config['meta_key'],
                                'value'   => absint($value),
                                'compare' => $config['compare'] ?? '=',
                                'type'    => 'NUMERIC'
                            ];
                        }
                        break;

                    case 'numeric_array':
                        // برای فیلتر قیمت، اطمینان حاصل کنید مقادیر به float تبدیل شوند
                        $values = array_map('floatval', explode('--', $value));
                        $filters[$key] = $values;

                        if (isset($config['meta_key'])) {
                            if ($config['compare'] === 'BETWEEN' && count($values) === 2) {
                                $filters['meta_query'][] = [
                                    'key'     => $config['meta_key'],
                                    'value'   => $values,
                                    'compare' => 'BETWEEN',
                                    'type'    => 'NUMERIC'
                                ];
                            }
                        }
                        break;

                    case 'string':
                        $filters[$key] = sanitize_text_field(urldecode($value));
                        break;

                    case 'term_array':
                        $values = array_map('sanitize_title', explode('--', $value));
                        $filters[$key] = $values;

                        if (isset($config['taxonomy'])) {
                            $filters['tax_query'][] = [
                                'taxonomy' => $config['taxonomy'],
                                'field'    => 'slug',
                                'terms'    => $values,
                                'operator' => 'IN'
                            ];
                        } elseif (isset($config['meta_key'])) {
                            foreach ($values as $val) {
                                $filters['meta_query'][] = [
                                    'key'   => $config['meta_key'],
                                    'value' => $val
                                ];
                            }
                        }
                        break;
                }
            } elseif (taxonomy_exists('pa_' . $key)) {
                $taxonomy = 'pa_' . $key;
                $values = array_map('sanitize_title', explode('--', $value));

                $filters['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $values,
                    'operator' => 'IN'
                ];
            } // 3. در نهایت بررسی کنید آیا کلید یک صفت سفارشی است
            elseif (mb_strpos($key, 'attribute-') === 0) {
                $taxonomy = str_replace('attribute-', '', $key);
                $values = array_map('sanitize_title', explode('--', $value));

                $filters['tax_query'][] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $values,
                    'operator' => 'IN'
                ];
            }

        }


        if (!empty($filters['tax_query'])) {
            $filters['tax_query']['relation'] = 'AND';
        }
        if (!empty($filters['meta_query'])) {
            $filters['meta_query']['relation'] = 'AND';
        }

        return $filters;
    }

    public function parse_request($wp)
    {
        if (!empty($wp->query_vars['wc_ajax_filters'])) {
            // اعتبارسنجی انعطاف‌پذیرتر برای پشتیبانی از ویژگی‌ها
            if (!preg_match('/^[a-z0-9\-_\/]+$/i', $wp->query_vars['wc_ajax_filters'])) {
                return;
            }

            $filter_string = sanitize_text_field($wp->query_vars['wc_ajax_filters']);
            $wp->query_vars['wc_ajax_filters_parsed'] = $this->parse_filters($filter_string);
        }
    }

    public function pre_get_posts($query)
    {

        // گسترش شرط برای پشتیبانی از تمام صفحات محصولات
        if (is_admin() ||
            !$query->is_main_query() ||
            (!is_shop() && !is_product_taxonomy() && !is_product_category()) ||
            !isset($query->query_vars['wc_ajax_filters_parsed']))
        {
            return;
        }

        $filters = $query->query_vars['wc_ajax_filters_parsed'];

        // اعمال meta_query
        if (!empty($filters['meta_query'])) {
            $existing_meta_query = $query->get('meta_query', []);
            $new_meta_query = array_merge($existing_meta_query, $filters['meta_query']);

            if (count($new_meta_query) > 1) {
                $new_meta_query['relation'] = 'AND';
            }

            $query->set('meta_query', $new_meta_query);
        }

        // اعمال tax_query
        if (!empty($filters['tax_query']) && count($filters['tax_query']) > 0) {
            $existing_tax_query = $query->get('tax_query', []);
            $new_tax_query = array_merge($existing_tax_query, $filters['tax_query']);
            $query->set('tax_query', $new_tax_query);
        }

        // اعمال orderby
        if (!empty($filters['orderby'])) {
            $query->set('orderby', $filters['orderby']);
        }

        // اعمال pagination
        if (!empty($filters['page'])) {
            $query->set('paged', $filters['page']);
        }
    }

    public function enqueue_assets()
    {
        if (is_shop() || is_product_category()) {
            wp_enqueue_script('nader-woocommerce-ajax-filters-price-slider', plugins_url('assets/price-slider.js', __FILE__), ['jquery'], '4.0', true);
            wp_enqueue_script('nader-woocommerce-ajax-filters', plugins_url('assets/filters.js', __FILE__), [
                'jquery',
                'select2',
                'nader-woocommerce-ajax-filters-price-slider'
            ], '4.0', true);

            // ایجاد nonce با طول عمر کوتاه‌تر
            $nonce = wp_create_nonce('nader_ajax_filters_' . get_current_user_id());
            wp_localize_script('nader-woocommerce-ajax-filters', 'nader_woocommerce_ajax_filters', [
                'ajaxurl'     => admin_url('admin-ajax.php'),
                'shop_url'    => rtrim(get_permalink(wc_get_page_id('shop')), '/'),
                'nonce'       => $nonce,
                'i18n'        => [
                    // عمومی
                    'common' => [
                        'price'    => __('Price', 'nader'),
                        'category' => __('Category', 'nader'),
                        'brand'    => __('Brand', 'nader'),
                        'rating'   => __('Rating', 'nader'),
                    ],

                    // مرتب‌سازی
                    'sorting' => [
                        'title'       => __('Sort by', 'nader'),
                        'menu_order'  => __('Default sorting', 'nader'),
                        'popularity'  => __('Sort by popularity', 'nader'),
                        'rating'      => __('Sort by average rating', 'nader'),
                        'date'        => __('Sort by latest', 'nader'),
                        'price_asc'   => __('Sort by price: low to high', 'nader'),
                        'price_desc'  => __('Sort by price: high to low', 'nader'),
                    ],

                    // وضعیت موجودی
                    'stock' => [
                        'title'        => __('Stock Status', 'nader'),
                        'in_stock'     => __('In Stock', 'nader'),
                        'out_of_stock' => __('Out of Stock', 'nader'),
                        'on_backorder' => __('On Backorder', 'nader'),
                    ],

                    // ویژگی‌ها (Attributes)
                    'attributes' => [
                        'cpu'   => __('CPU', 'nader'),
                        'color' => __('Color', 'nader'),
                        // سایر ویژگی‌ها
                    ],

                    // پیام‌ها
                    'messages' => [
                        'clear_all' => __('Clear all filters', 'nader'),
                        'selected'  => __('Selected filters', 'nader'),
                        'no_result' => __('No results found', 'nader'),
                    ],

                    // Select2
                    'select2' => [
                        'placeholder' => __('Select an option', 'nader'),
                        'no_results'  => __('No results found', 'nader'),
                    ],
                ],
                'price_range' => $this->get_product_price_range()
            ]);

            wp_enqueue_style('nader-woocommerce-ajax-filters', plugins_url('assets/filters.css', __FILE__), ['select2'], '4.0');
        }
    }

    private function get_product_price_range()
    {
        global $wpdb;

        // استفاده از prepare برای ایمن‌سازی کوئری
        $query = $wpdb->prepare("
        SELECT MIN(meta_value + 0) as min, MAX(meta_value + 0) as max
        FROM {$wpdb->postmeta}
        WHERE meta_key = %s AND meta_value != ''
    ", '_price');

        $prices = $wpdb->get_row($query);

        return [
            'min' => floor($prices->min),
            'max' => ceil($prices->max)
        ];
    }

    private function build_meta_query($config, $value)
    {
        // اعتبارسنجی مقادیر مجاز برای compare
        if (!in_array($config['compare'], $this->allowed_compares)) {
            wp_send_json_error('Invalid compare operator', 400);
        }

        // اعتبارسنجی نوع مقدار
        if (!in_array($config['value_type'], $this->allowed_types)) {
            $config['value_type'] = 'CHAR'; // مقدار پیش‌فرض
        }

        // برای فیلتر قیمت، نوع را همیشه NUMERIC قرار دهید و مقادیر را به float تبدیل کنید
        if ($config['key'] === '_price') {
            $config['value_type'] = 'NUMERIC';

            if (is_array($value)) {
                $value = array_map('floatval', $value);
            } else {
                $value = floatval($value);
            }
        }

        // برای فیلتر رتبه‌بندی، مقادیر را به float تبدیل کنید
        if ($config['key'] === '_wc_average_rating') {
            if (is_array($value)) {
                $value = array_map('floatval', $value);
            } else {
                $value = floatval($value);
            }
        }

        // برای فیلتر وضعیت موجودی، مقادیر را به حالت استاندارد ووکامرس تبدیل کنید
        if ($config['key'] === '_stock_status') {
            $value = $this->map_stock_values($value);
        }

        return [
            'key'     => sanitize_key($config['key']),
            'value'   => $value,
            'compare' => $config['compare'],
            'type'    => $config['value_type']
        ];
    }

    private function build_attributes($name, $taxes)
    {
        $attr_tax_name = 'pa_' . str_replace('attribute-', '', sanitize_key($name));

        // بررسی وجود taxonomy
        if (!taxonomy_exists($attr_tax_name)) {
            wp_send_json_error("Taxonomy {$attr_tax_name} does not exist", 400);
        }

        // ضدعفونی مقادیر taxonomy
        $clean_taxes = array_map('sanitize_title', (array)$taxes);

        return [
            'taxonomy' => $attr_tax_name,
            'field'    => 'slug',
            'terms'    => $clean_taxes,
            'operator' => 'IN'
        ];
    }

    private function build_orderby(&$args, $orderby)
    {
        // لیست مقادیر مجاز
        $allowed = ['price', 'date', 'price-desc', 'menu_order', 'rating', 'popularity'];

        if (!in_array($orderby, $allowed)) {
            $orderby = 'date'; // مقدار پیش‌فرض
        }

        switch ($orderby) {
            case 'price':
                $args['meta_key'] = '_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'ASC';
                break;
            case 'price-desc':
                $args['meta_key'] = '_price';
                $args['orderby'] = 'meta_value_num';
                $args['order'] = 'DESC';
                break;
            case 'menu_order':
            case 'date':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
            case 'rating';
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = '_wc_average_rating';
                break;
            case 'popularity';
                $args['meta_key'] = 'total_sales';
                $args['orderby'] = 'meta_value_num';
                break;
        }
    }

    private function build_query_args($filters)
    {
        $post_per_page = Nader_Settings::instance()->get_setting('archive_products_per_page');
        $args = [
            'post_type'      => 'product',
            'meta_query'     => [],
            'tax_query'      => [],
            'posts_per_page' => $post_per_page,
            'no_found_rows'  => false,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        foreach ($filters as $type => $value) {
            $filter_config = $this->filter_types[$type];

            // برای فیلتر قیمت، مطمئن شوید که مقادیر عددی هستند
            if ($type === 'price' && is_array($value)) {
                $value = array_map('floatval', $value);
            }

            switch ($filter_config['type']) {
                case 'meta':
                    // برای فیلتر قیمت، اطمینان حاصل کنید مقادیر به float تبدیل شوند
                    if ($type === 'price' && is_array($value)) {
                        $value = array_map('floatval', $value);
                    }
                    $args['meta_query'][] = $this->build_meta_query($filter_config, $value);
                    break;
                case 'page':
                    $args['paged'] = $value;
                    break;
                case 'orderby':
                    $this->build_orderby($args, $value);
                    break;
                case 'taxonomy':
                    $args['tax_query'][] = [
                        'taxonomy' => $filter_config['taxonomy'],
                        'field'    => 'slug',
                        'terms'    => $value,
                        'operator' => 'IN'
                    ];
                    break;
                default:
                    if (mb_strpos($type, 'attribute') !== false) {
                        $args['tax_query'][] = $this->build_attributes($type, $value);
                    }
                    break;
            }

        }

        return $args;
    }

    public function ajax_handler()
    {

        // بررسی منبع درخواست
        $referer = wp_get_referer();
        if (!$referer || !in_array(parse_url($referer, PHP_URL_HOST), [$_SERVER['HTTP_HOST']])) {
            wp_send_json_error('Invalid request source', 403);
        }

        $user_id = get_current_user_id();
        $nonce = $_POST['nonce'] ?? '';

        if (!wp_verify_nonce($nonce, 'nader_ajax_filters_' . $user_id)) {
            wp_send_json_error('Invalid nonce', 403);
        }

        // اعتبارسنجی ساختار فیلترها
        $filters = isset($_POST['filters']) && is_array($_POST['filters']) ? $_POST['filters'] : [];

        // اعتبارسنجی ساختار فیلترها
        if (!is_array($filters)) {
            wp_send_json_error('Invalid filters format', 400);
        }

        // تابع ضدعفونی بازگشتی
        $sanitize_value = function(&$value, $key) {
            if (is_array($value)) {
                array_walk_recursive($value, function(&$v) {
                    $v = sanitize_text_field($v);
                });
            } else {
                $value = sanitize_text_field($value);
            }
        };

        array_walk($filters, $sanitize_value);


        $args = $this->build_query_args($filters);

        if (!empty($args['tax_query'])) {
            $args['tax_query']['relation'] = 'AND';
        }
        if (!empty($args['meta_query'])) {
            $args['meta_query']['relation'] = 'AND';
        }

        ob_start();
        $products = new WP_Query($args);
        $paged = $args['paged'];
        $last_page = $products->max_num_pages;
        if ($paged > 1 && $products->post_count === 0) {
            // محاسبه آخرین صفحه معتبر
            $last_page = max(1, $products->max_num_pages);

            // اگر صفحه درخواستی بزرگتر از آخرین صفحه معتبر بود
            if ($paged > $last_page) {
                // اجرای مجدد کوئری با آخرین صفحه معتبر
                $args['paged'] = $last_page;
                $products = new WP_Query($args);
            }
        }

        if ($products->have_posts()) {
            if ($paged > $last_page) {
                echo '<p class="page-not-found nader-before-box mb-4">' . sprintf(
                        __('صفحه درخواستی وجود ندارد. در حال نمایش صفحه %d از %d.', 'nader'),
                        $last_page,
                        $products->max_num_pages
                    ) . '</p>';
            }

            woocommerce_product_loop_start();
            while ($products->have_posts()) {
                $products->the_post();
                get_template_part('parts/product/card/card', 'simple');
            }
            woocommerce_product_loop_end();
        } else {
            do_action('woocommerce_no_products_found');
        }

        wp_reset_postdata();

        wp_send_json_success([
            'args'          => $args,
            'html'          => ob_get_clean(),
            'count'         => $products->found_posts,
            'max_num_pages' => $products->max_num_pages,
            'current_page'  => $args['paged']
        ]);
    }

    public function shortcode_handler($atts = [])
    {
        ob_start();
        $template_path = plugin_dir_path(__FILE__) . 'filters.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="error">Filters template not found!</div>';
        }
        return ob_get_clean();
    }


}

// در انتهای کلاس قبل از خط new Nader_Woocommerce_Ajax_Filters();
register_activation_hook(__FILE__, function() {
    $instance = new Nader_Woocommerce_Ajax_Filters();
    $instance->init_rewrites();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

new Nader_Woocommerce_Ajax_Filters();
