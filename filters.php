<?php
defined('ABSPATH') || exit;
?>

<div class="nader-woocommerce-ajax-filters filter-section nader-wc-ajax-filters-selected-filters" style="display: none;">
    <label><?php esc_html_e('Selected filters', 'nader'); ?></label>
    <div class="selected-filters-container"></div>
    <button class="clear-all-filters" style="display: none;">
        <?php esc_html_e('Clear all filters', 'nader'); ?>
    </button>
</div>


<!-- Price Filter -->
<div class="nader-woocommerce-ajax-filters filter-section nader-wc-ajax-filters-price-slider">
    <label><?php esc_html_e('Price', 'nader'); ?></label>

    <div class="price-slider">
        <div class="slider-track"></div>
        <div class="slider-thumb min-thumb"></div>
        <div class="slider-thumb max-thumb"></div>
    </div>
    <div class="price-inputs">
        <input type="number" class="min-price" placeholder="حداقل" data-type="number">
        <span>تا</span>
        <input type="number" class="max-price" placeholder="حداکثر" data-type="number">
    </div>
</div>


<div class="nader-woocommerce-ajax-filters filter-section">
    <label for="category-filter"><?php esc_html_e('Categories', 'nader'); ?></label>

    <select multiple id="category-filter"
            class="filter-type-taxonomy filter-ui-type-select"
            data-filter-slug="category">
        <?php foreach (get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true]) as $category) : ?>
            <option value="<?php echo esc_attr(urldecode($category->slug)); ?>">
                <?php echo esc_html($category->name); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


<div class="nader-woocommerce-ajax-filters filter-section">
    <label for="orderby-filter"><?php echo esc_html__('Sort by', 'woocommerce'); ?></label>

    <select class="filter-type-taxonomy filter-ui-type-select"
            aria-label="سفارش خرید"
            data-filter-slug="orderby">
        <option value="menu_order" selected="selected">مرتب‌سازی پیش‌فرض</option>
        <option value="popularity">مرتب‌سازی بر اساس محبوبیت</option>
        <option value="rating">مرتب‌سازی بر اساس امتیاز</option>
        <option value="date">مرتب‌سازی بر اساس جدیدترین</option>
        <option value="price">مرتب‌سازی بر اساس ارزانترین</option>
        <option value="price-desc">مرتب‌سازی بر اساس گرانترین</option>
    </select>
</div>


<div class="nader-woocommerce-ajax-filters filter-section">
    <label for="cpu-filter"><?php esc_html_e('Cpu', 'nader'); ?></label>

    <div id="cpu-filter"
         class="filter-type-taxonomy filter-ui-type-checkbox">
        <?php foreach (get_terms(['taxonomy' => 'pa_cpu', 'hide_empty' => false]) as $item) : ?>
            <label class="checkbox-item color-palette">
                <input type="checkbox"
                       class="checkbox"
                       data-filter-slug="attribute-cpu"
                       value="<?php echo esc_attr(urldecode($item->slug)); ?>">
                <span class="checkmark"></span>
                <span class="checkbox-name"><?php echo esc_html($item->name) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

</div>


<div class="nader-woocommerce-ajax-filters filter-section">
    <label for="brand-filter"><?php esc_html_e('Brands', 'nader'); ?></label>

    <?php
    $brands = get_terms([
        'taxonomy'   => 'product_brand',
        'hide_empty' => false,
        'orderby'    => 'name',
        'parent'     => 0, // Get top-level terms first
    ]);

    function display_brands_hierarchy($brands, $taxonomy)
    {
        foreach ($brands as $brand) {
            echo '<label class="checkbox-item">';
            echo '<input type="checkbox" class="checkbox" data-filter-slug="brand" value="' . esc_attr(urldecode($brand->slug)) . '">';
            echo '<span class="checkmark"></span><span class="checkbox-name">' . esc_html($brand->name) . '</span>';
            echo '</label>';

            // Fetch child terms
            $child_brands = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'parent'     => $brand->term_id,
            ]);

            if (!empty($child_brands)) {
                echo '<div class="child-terms">';
                display_brands_hierarchy($child_brands, $taxonomy);
                echo '</div>';
            }
        }
    }

    ?>
    <div id="brand-filter" class="filter-type-taxonomy filter-ui-type-checkbox">
        <?php display_brands_hierarchy($brands, 'product_brand'); ?>
    </div>

</div>


<div class="nader-woocommerce-ajax-filters filter-section">
    <label for="colors-filter"><?php esc_html_e('Colors', 'nader'); ?></label>

    <div id="colors-filter"
         class="filter-type-taxonomy filter-ui-type-color filter-ui-type-checkbox">
        <?php foreach (get_terms(['taxonomy' => 'pa_color', 'hide_empty' => true]) as $color) : ?>
            <label class="checkbox-item color-palette">
                <input type="checkbox"
                       class="checkbox"
                       data-filter-slug="attribute-color"
                       value="<?php echo esc_attr(urldecode($color->slug)) ?>">
                <span class="color checkmark"
                      style="background: <?php echo esc_attr(get_term_meta($color->term_id, 'color', true)); ?>"></span>
                <span class="checkbox-name"><?php echo esc_html($color->name) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

</div>


<!-- Rating Filter -->
<div class="nader-woocommerce-ajax-filters filter-section">
    <label for="rating-filter"><?php esc_html_e('Minimum Rating', 'nader'); ?></label>
    <select id="rating-filter" class="rating-filter" data-filter-slug="rating">
        <option value=""><?php esc_html_e('Any Rating', 'nader'); ?></option>
        <?php for ($i = 1; $i <= 5; $i++) : ?>
            <option value="<?= $i ?>"><?= $i ?><?php esc_html_e('Stars & Up', 'nader'); ?></option>
        <?php endfor; ?>
    </select>
</div>


<!-- Stock Status Filter -->
<div class="nader-woocommerce-ajax-filters filter-section">
    <label for="stock-filter"><?php esc_html_e('Stock Status', 'nader'); ?></label>
    <select id="stock-filter" class="stock-filter" data-filter-slug="stock" multiple>
        <option value="in-stock"><?php esc_html_e('In Stock', 'nader'); ?></option>
        <option value="out-of-stock"><?php esc_html_e('Out of Stock', 'nader'); ?></option>
        <option value="on-backorder"><?php esc_html_e('On Backorder', 'nader'); ?></option>
    </select>
</div>
