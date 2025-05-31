jQuery(function ($) {
    // حالت‌های فیلتر
    const state = {
        filters: {},
        selectedCheckboxes: {}
    };

    // مقداردهی اولیه Select2
    function initializeSelect2() {
        $('.filter-section select[multiple]').each(function () {
            $(this).select2({
                width: '100%',
                placeholder: $(this).data('placeholder') || nader_woocommerce_ajax_filters.i18n.select2.placeholder,
                dropdownParent: $(this).closest('.filter-section'),
                language: {noResults: () => nader_woocommerce_ajax_filters.i18n.select2.no_results},
                rtl: $('body').hasClass('rtl')
            });
        });

        $('.filter-section select:not([multiple])').each(function () {
            $(this).select2({
                width: '100%',
                rtl: $('body').hasClass('rtl'),
                placeholder: $(this).data('placeholder') || nader_woocommerce_ajax_filters.i18n.select2.placeholder,
                language: {noResults: () => nader_woocommerce_ajax_filters.i18n.select2.no_results},
                dropdownParent: $(this).closest('.filter-section'),
                minimumResultsForSearch: 10
            });
        });
    }

    // مدیریت تغییرات در selectها
    function handleSelectChanges() {
        $('.nader-woocommerce-ajax-filters.filter-section').on('change', 'select', function (e) {
            const $el = $(e.target);
            const slug = $el.data('filter-slug');
            const value = $el.val();

            if (!value || (Array.isArray(value) && value.length === 0)) {
                delete state.filters[slug];
            } else {
                state.filters[slug] = value;
            }

            applyFilter();
        });
    }

    // مدیریت تغییرات در checkboxها
    function handleCheckboxChanges() {
        $('.nader-woocommerce-ajax-filters.filter-section').on('change', 'input[type="checkbox"]', function () {
            const $checkbox = $(this);
            const group = $checkbox.data('filter-slug');
            const value = $checkbox.val();

            // مقداردهی اولیه آرایه اگر وجود نداشته باشد
            if (!state.selectedCheckboxes[group]) {
                state.selectedCheckboxes[group] = [];
            }

            // اضافه یا حذف مقدار از آرایه
            if ($checkbox.prop('checked')) {
                state.selectedCheckboxes[group].push(value);
            } else {
                const index = state.selectedCheckboxes[group].indexOf(value);
                if (index !== -1) {
                    state.selectedCheckboxes[group].splice(index, 1);
                }
            }

            // به‌روزرسانی filters
            if (state.selectedCheckboxes[group] && state.selectedCheckboxes[group].length > 0) {
                state.filters[group] = state.selectedCheckboxes[group];
            } else {
                delete state.filters[group];
            }

            applyFilter();
        });
    }

    // مدیریت تغییرات قیمت
    function handlePriceChanges() {
        const $minPrice = $('.nader-woocommerce-ajax-filters .price-inputs .min-price');
        const $maxPrice = $('.nader-woocommerce-ajax-filters .price-inputs .max-price');

        // تغییر مقدار عددی
        $minPrice.add($maxPrice).on('change', function () {
            updatePriceFilter($minPrice.val(), $maxPrice.val());
        });

        // تغییر از طریق اسلایدر
        $('.nader-woocommerce-ajax-filters .slider-thumb').on('mouseup mouseout mouseleave touchend', function (e) {
            if (e.which !== 1) return;
            updatePriceFilter($minPrice.val(), $maxPrice.val());
        });

        function updatePriceFilter(min, max) {
            state.filters.price = [min, max];
            applyFilter();
        }
    }

    // مدیریت صفحه‌بندی
    function handlePagination() {
        $(document).on('click', '.woocommerce-pagination a:not(.current)', function (e) {
            e.preventDefault();
            state.filters.page = parseInt($(this).text());
            applyFilter();
        });
    }

    // اعمال فیلترها از طریق AJAX
    function applyFilter() {
        $.ajax({
            url: nader_woocommerce_ajax_filters.ajaxurl,
            type: 'POST',
            data: {
                action: 'nader_woocommerce_ajax_filters',
                nonce: nader_woocommerce_ajax_filters.nonce,
                filters: state.filters
            },
            beforeSend: showLoadingState,
            success: handleAjaxSuccess,
            complete: hideLoadingState
        });
    }

    // نمایش حالت بارگذاری
    function showLoadingState() {
        $('.nader-woocommerce-ajax-filters, .product-content-section')
            .addClass('nader-woocommerce-ajax-filters-loading');
    }

    // پنهان کردن حالت بارگذاری
    function hideLoadingState() {
        $('.nader-woocommerce-ajax-filters, .product-content-section')
            .removeClass('nader-woocommerce-ajax-filters-loading');
    }

    // مدیریت موفقیت‌آمیز پاسخ AJAX
    function handleAjaxSuccess(response) {
        if (!response.success) return;

        // حذف محتوای قبلی
        $('.product-content-section .woocommerce-pagination, .product-content-section ul.products, ' +
            '.product-content-section .woocommerce-no-products-found, .product-content-section .page-not-found').remove();

        // اضافه کردن محتوای جدید
        $('.product-content-section').append(response.data.html);

        updateSelectedFiltersDisplay();

        // به‌روزرسانی صفحه‌بندی
        updatePagination(response.data);

        // تصحیح صفحه اگر خارج از محدوده باشد
        if (state.filters.page && response.data.count > 0 && state.filters.page > response.data.max_num_pages) {
            state.filters.page = 1;
        }

        // به‌روزرسانی URL
        buildUrl();
    }

    // به‌روزرسانی صفحه‌بندی
    function updatePagination(data) {
        if (data.count > 0 && data.max_num_pages > 1) {
            const pagination = generatePagination(data.current_page, data.max_num_pages);
            $('.product-content-section').append(pagination);
        }
    }

    // تولید HTML صفحه‌بندی
    function generatePagination(current, total) {
        let html = '<nav class="woocommerce-pagination" aria-label="صفحه‌بندی محصول"><ul class="page-numbers">';

        for (let i = 1; i <= total; i++) {
            html += `<li><a class="${i === current ? 'current' : ''}">${i}</a></li>`;
        }

        html += '</ul></nav>';
        return html;
    }

    // ساخت URL بر اساس فیلترها
    function buildUrl() {
        const order = ['price', 'category', 'brand', 'attribute', 'rating', 'stock', 'orderby', 'page'];
        const parts = [];
        let hasAnyFilter = false;

        order.forEach(key => {
            if (key === 'attribute') {
                Object.keys(state.filters).forEach(filterKey => {
                    if (filterKey.startsWith('attribute-') && state.filters[filterKey]?.length) {
                        const attrName = filterKey.replace('attribute-', '');
                        const values = state.filters[filterKey].join('--');
                        parts.push(`${attrName}/${encodeURIComponent(values)}`);
                        hasAnyFilter = true;
                    }
                });
            } else if (state.filters[key] && (Array.isArray(state.filters[key]) ? state.filters[key].length : true)) {
                const values = Array.isArray(state.filters[key]) ?
                    state.filters[key].join('--') :
                    state.filters[key];
                parts.push(`${key}/${encodeURIComponent(values)}`);
                hasAnyFilter = true;
            }
        });

        let url = nader_woocommerce_ajax_filters.shop_url;
        url += hasAnyFilter ? '/' + parts.join('/') + '/' : '/';

        history.replaceState(null, '', url);
    }

    // تجزیه URL و اعمال فیلترها
    function applyFiltersFromUrl() {
        const urlFilters = parseFilterUrl();

        // اعمال فیلترهای شناخته شده
        applyKnownFilters(urlFilters);

        // اعمال فیلترهای داینامیک (مانند attributeها)
        applyDynamicFilters(urlFilters);

        updateSelectedFiltersDisplay()

    }

    // تجزیه URL
    function parseFilterUrl() {
        const path = window.location.pathname;
        const segments = path.split('/');
        const filters = {};

        // حذف بخش shop از ابتدای URL
        if (segments[1] && segments[1].startsWith('shop')) {
            segments.splice(1, 1);
        }

        // پردازش بخش‌های URL
        for (let i = 1; i < segments.length; i += 2) {
            if (!segments[i + 1]) continue;

            const key = segments[i];
            const value = segments[i + 1];

            filters[key] = value.includes('--') ? value.split('--') : value;
        }

        return filters;
    }

    // اعمال فیلترهای شناخته شده
    function applyKnownFilters(filters) {
        const filterHandlers = {
            price: function (value) {
                if (value.length === 2) {
                    $('.min-price').val(value[0]);
                    $('.max-price').val(value[1]);
                    state.filters.price = value;

                    // به‌روزرسانی اسلایدر با استفاده از نمونه موجود
                    if (window.priceSliderInstance) {
                        window.priceSliderInstance.currentMin = parseInt(value[0]);
                        window.priceSliderInstance.currentMax = parseInt(value[1]);
                        window.priceSliderInstance.updateSlider();
                        window.priceSliderInstance.updateInputs();
                    }
                }
            },
            category: function (value) {
                // تبدیل مقدار به آرایه اگر رشته باشد
                const values = Array.isArray(value) ? value : [value];
                $('#category-filter').val(values).trigger('change.select2');
                state.filters.category = values;
            },
            brand: function (value) {
                // تبدیل مقدار به آرایه اگر رشته باشد
                const values = Array.isArray(value) ? value : [value];

                // بازنشانی تمام چک‌باکس‌ها
                $('input[data-filter-slug="brand"]').prop('checked', false);

                // انتخاب مقادیر جدید
                values.forEach(brand => {
                    $('input[data-filter-slug="brand"][value="' + brand + '"]').prop('checked', true);
                });

                state.selectedCheckboxes.brand = values;
                state.filters.brand = values;
            },
            rating: function (value) {
                // تبدیل مقدار به عدد اگر رشته باشد
                const ratingValue = typeof value === 'string' ? parseInt(value) : value;
                $('#rating-filter').val(ratingValue).trigger('change.select2');
                state.filters.rating = ratingValue;
            },
            stock: function (value) {
                // تبدیل مقدار به آرایه اگر رشته باشد
                const values = Array.isArray(value) ? value : [value];
                $('#stock-filter').val(values).trigger('change.select2');
                state.filters.stock = values;
            },
            orderby: function (value) {
                $('select[data-filter-slug="orderby"]').val(value).trigger('change.select2');
                state.filters.orderby = value;
            }
        };

        Object.keys(filterHandlers).forEach(key => {
            if (filters[key]) {
                filterHandlers[key](filters[key]);
                delete filters[key];
            }
        });
    }

    // اعمال فیلترهای داینامیک
    function applyDynamicFilters(filters) {
        Object.keys(filters).forEach(filter => {
            const values = Array.isArray(filters[filter]) ?
                filters[filter] :
                [filters[filter]];

            const filterSlug = 'attribute-' + filter;

            // حذف انتخاب‌های قبلی
            $('input[data-filter-slug="' + filterSlug + '"]').prop('checked', false);

            // مقداردهی اولیه آرایه
            if (!state.selectedCheckboxes[filterSlug]) {
                state.selectedCheckboxes[filterSlug] = [];
            }

            // اعمال مقادیر جدید
            values.forEach(value => {
                $('input[data-filter-slug="' + filterSlug + '"][value="' + value + '"]').prop('checked', true);
                state.selectedCheckboxes[filterSlug].push(value);
            });

            // به‌روزرسانی state.filters
            if (state.selectedCheckboxes[filterSlug].length > 0) {
                state.filters[filterSlug] = state.selectedCheckboxes[filterSlug];
            }
        });
    }

    // تابع جدید برای نمایش فیلترهای انتخاب شده
    // تابع جدید برای نمایش فیلترهای انتخاب شده
    function updateSelectedFiltersDisplay() {
        const $container = $('.selected-filters-container');
        const $clearAllBtn = $('.clear-all-filters');
        $container.empty();

        let hasFilters = false;

        // نمایش فیلترهای انتخاب شده
        Object.keys(state.filters).forEach(filterKey => {
            const values = state.filters[filterKey];
            if (!values || (Array.isArray(values) && values.length === 0)) return;

            hasFilters = true;
            const filterName = getFilterName(filterKey);

            // حالت خاص برای فیلتر قیمت
            if (filterKey === 'price' && Array.isArray(values) && values.length === 2) {
                const displayValue = `${values[0]} - ${values[1]}`;
                $container.append(createFilterTag(filterKey, values, filterName, displayValue));
            }
            else if (Array.isArray(values)) {
                values.forEach(value => {
                    $container.append(createFilterTag(filterKey, value, filterName, value));
                });
            } else {
                $container.append(createFilterTag(filterKey, values, filterName, values));
            }
        });

        // نمایش/پنهان کردن دکمه حذف همه
        $clearAllBtn.toggle(hasFilters);
        $('.nader-wc-ajax-filters-selected-filters').toggle(hasFilters);
    }


    // تابع جدید برای ایجاد تگ فیلتر
    function createFilterTag(filterKey, value, filterName, displayValue) {
        // دریافت متن واقعی فیلتر از عنصر .checkbox-name
        let actualDisplayValue = displayValue;

        // برای فیلترهای checkbox، متن واقعی را از صفحه دریافت می‌کنیم
        if (filterKey !== 'price' && filterKey !== 'orderby' && filterKey !== 'rating') {
            const $element = $(`input[data-filter-slug="${filterKey}"][value="${value}"]`)
                .closest('.checkbox-item')
                .find('.checkbox-name');

            if ($element.length) {
                actualDisplayValue = $element.text().trim();
            }
        }

        // برای فیلترهای select
        if (filterKey === 'category' || filterKey === 'stock' || filterKey === 'rating') {
            const $option = $(`select[data-filter-slug="${filterKey}"] option[value="${value}"]`);
            if ($option.length) {
                actualDisplayValue = $option.text().trim();
            }
        }

        return $(`
        <div class="filter-tag" data-filter-key="${filterKey}" data-filter-value="${value}">
            <span class="remove-filter cursor-pointer">&times;</span>
            <span>${filterName}: ${actualDisplayValue}</span>
        </div>
    `);
    }

    // تابع جدید برای گرفتن نام نمایشی فیلتر
    // تابع ساده‌شده برای گرفتن نام نمایشی فیلتر
    function getFilterName(filterKey) {
        const i18n = nader_woocommerce_ajax_filters.i18n;

        // بررسی دسته‌بندی‌های مختلف
        if (i18n.common[filterKey]) return i18n.common[filterKey];
        if (i18n.sorting[filterKey]) return i18n.sorting[filterKey];
        if (i18n.stock[filterKey]) return i18n.stock[filterKey];

        // برای attributeها
        if (filterKey.startsWith('attribute-')) {
            const attrName = filterKey.replace('attribute-', '');
            return i18n.attributes[attrName] ||
                attrName.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        }

        return filterKey;
    }

    // تابع جدید برای گرفتن مقدار نمایشی فیلتر
    function getFilterValueDisplay(filterKey, value) {
        const i18n = nader_woocommerce_ajax_filters.i18n;
        // وضعیت موجودی
        if (filterKey === 'stock') {
            return i18n.stock[value] || value;
        }
        // مرتب‌سازی
        if (filterKey === 'orderby') {
            return i18n.sorting[value] || value;
        }

        return value;
    }

    // تابع جدید برای حذف فیلتر
    function removeFilter(filterKey, value) {
        // حالت خاص برای فیلتر قیمت
        if (filterKey === 'price') {
            delete state.filters.price;

            // بازنشانی به مقادیر اولیه
            const priceRange = nader_woocommerce_ajax_filters.price_range;
            const minPrice = priceRange.min;
            const maxPrice = priceRange.max;

            $('.min-price').val(minPrice);
            $('.max-price').val(maxPrice);

            // بازنشانی اسلایدر
            if (window.priceSliderInstance) {
                window.priceSliderInstance.currentMin = minPrice;
                window.priceSliderInstance.currentMax = maxPrice;
                window.priceSliderInstance.updateSlider();
                window.priceSliderInstance.updateInputs();
            }
        } else if (Array.isArray(state.filters[filterKey])) {
            const index = state.filters[filterKey].indexOf(value);
            if (index !== -1) {
                state.filters[filterKey].splice(index, 1);
            }

            if (state.filters[filterKey].length === 0) {
                delete state.filters[filterKey];
            }
        } else {
            delete state.filters[filterKey];
        }

        // به روز رسانی UI مربوطه
        updateUIAfterFilterRemoval(filterKey, value);
        applyFilter();
    }

    // تابع جدید برای به روز رسانی UI پس از حذف فیلتر
    function updateUIAfterFilterRemoval(filterKey, value) {
        // برای selectها
        if (filterKey === 'orderby' || filterKey === 'rating' || filterKey === 'stock') {
            $(`select[data-filter-slug="${filterKey}"]`).val('').trigger('change.select2');
        }
        // برای selectهای چندگانه
        else if (filterKey === 'category') {
            const currentValues = $(`#${filterKey}-filter`).val() || [];
            const newValues = currentValues.filter(v => v !== value);
            $(`#${filterKey}-filter`).val(newValues).trigger('change.select2');
        }
        // برای checkboxها
        else {
            $(`input[data-filter-slug="${filterKey}"][value="${value}"]`).prop('checked', false);

            // به روز رسانی state.selectedCheckboxes
            if (state.selectedCheckboxes[filterKey]) {
                const index = state.selectedCheckboxes[filterKey].indexOf(value);
                if (index !== -1) {
                    state.selectedCheckboxes[filterKey].splice(index, 1);
                }

                if (state.selectedCheckboxes[filterKey].length === 0) {
                    delete state.selectedCheckboxes[filterKey];
                }
            }
        }
    }

    // تابع جدید برای حذف همه فیلترها
    function clearAllFilters() {
        // حذف از state
        state.filters = {};
        state.selectedCheckboxes = {};

        // بازنشانی UI
        $('.nader-woocommerce-ajax-filters select').val('').trigger('change.select2');
        $('.nader-woocommerce-ajax-filters input[type="checkbox"]').prop('checked', false);

        // بازنشانی قیمت به مقادیر اولیه
        const priceRange = nader_woocommerce_ajax_filters.price_range;
        const minPrice = priceRange.min;
        const maxPrice = priceRange.max;

        $('.min-price').val(minPrice);
        $('.max-price').val(maxPrice);

        // بازنشانی اسلایدر
        if (window.priceSliderInstance) {
            window.priceSliderInstance.currentMin = minPrice;
            window.priceSliderInstance.currentMax = maxPrice;
            window.priceSliderInstance.updateSlider();
            window.priceSliderInstance.updateInputs();
        }

        applyFilter();
    }


    // مقداردهی اولیه
    initializeSelect2();
    handleSelectChanges();
    handleCheckboxChanges();
    handlePriceChanges();
    handlePagination();

    $(window).on('load', applyFiltersFromUrl);

    // در انتهای فایل، بعد از رویدادهای موجود، این رویدادها را اضافه کنید:
    $(document).on('click', '.remove-filter', function() {
        const $tag = $(this).closest('.filter-tag');
        const filterKey = $tag.data('filter-key');
        const filterValue = $tag.data('filter-value');
        removeFilter(filterKey, filterValue);
    });

    $(document).on('click', '.clear-all-filters', clearAllFilters);

});