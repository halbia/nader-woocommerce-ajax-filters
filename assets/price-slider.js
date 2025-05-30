jQuery(document).ready(function($) {

    if (typeof PriceSlider === 'undefined') {
        window.PriceSlider = class PriceSlider {
            constructor(slider) {

                this.slider = $(slider).find('.price-slider');
                this.track = $(slider).find('.slider-track');
                this.minThumb = $(slider).find('.min-thumb');
                this.maxThumb = $(slider).find('.max-thumb');
                this.minInput = $(slider).find('.min-price');
                this.maxInput = $(slider).find('.max-price');

                this.minPrice = 0;
                this.maxPrice = 1000000; // حداکثر قیمت پیش‌فرض
                this.currentMin = 0;
                this.currentMax = this.maxPrice;

                this.isRTL = $('body').hasClass('rtl');

                this.init();
            }

            init() {
                this.setupSlider();
                this.setupEvents();
                this.updateSlider();
            }

            setupSlider() {
                // دریافت قیمت‌های واقعی از ووکامرس
                this.minPrice = nader_woocommerce_ajax_filters.price_range.min;
                this.maxPrice = nader_woocommerce_ajax_filters.price_range.max;
                this.currentMin = this.minPrice;
                this.currentMax = this.maxPrice;
                this.updateInputs();
                this.updateSlider();
            }

            setupEvents() {
                const self = this;

                // رویدادهای کشیدن دسته‌ها
                this.minThumb.on('mousedown', function(e) {
                    self.dragStart(e, 'min');
                });

                this.maxThumb.on('mousedown', function(e) {
                    self.dragStart(e, 'max');
                });

                // رویدادهای تغییر فیلدهای عددی
                this.minInput.on('change', function() {
                    const rawValue = parseInt($(this).val().replace(/,/g, ''));
                    const value = Math.min(rawValue, self.currentMax);
                    self.currentMin = isNaN(value) ? self.minPrice : value;
                    self.updateSlider();
                    self.updateInputs();
                });

                this.maxInput.on('change', function() {
                    const rawValue = parseInt($(this).val().replace(/,/g, ''));
                    const value = Math.max(rawValue, self.currentMin);
                    self.currentMax = isNaN(value) ? self.maxPrice : value;
                    self.updateSlider();
                    self.updateInputs();
                });
            }

            dragStart(e, type) {
                e.preventDefault();
                const self = this;

                $(document).on('mousemove', function(e) {
                    self.dragMove(e, type);
                });

                $(document).on('mouseup', function() {
                    $(document).off('mousemove mouseup');
                });
            }

            dragMove(e, type) {
                const sliderOffset = this.slider.offset().left;
                const sliderWidth = this.slider.width();
                let position = e.pageX - sliderOffset;

                // محدودیت موقعیت
                position = Math.max(0, Math.min(position, sliderWidth));

                // معکوس کردن موقعیت برای RTL
                if(this.isRTL) {
                    position = sliderWidth - position;
                }

                // محاسبه قیمت
                const price = Math.round((position / sliderWidth) * (this.maxPrice - this.minPrice) + this.minPrice);

                // به‌روزرسانی مقادیر
                if (type === 'min') {
                    this.currentMin = Math.min(price, this.currentMax);
                } else {
                    this.currentMax = Math.max(price, this.currentMin);
                }

                this.updateSlider();
                this.updateInputs();
            }

            updateSlider() {
                const minPosition = ((this.currentMin - this.minPrice) / (this.maxPrice - this.minPrice)) * 100;
                const maxPosition = ((this.currentMax - this.minPrice) / (this.maxPrice - this.minPrice)) * 100;

                // تنظیم موقعیت بر اساس جهت
                if(this.isRTL) {
                    this.minThumb.css('right', `calc(${minPosition}% + 5px)`);
                    this.maxThumb.css('right', `calc(${maxPosition}% - 5px)`);
                    this.track.css({
                        'right': `${minPosition}%`,
                        'width': `${maxPosition - minPosition}%`
                    });
                } else {
                    this.minThumb.css('left', `${minPosition}%`);
                    this.maxThumb.css('left', `${maxPosition}%`);
                    this.track.css({
                        'left': `${minPosition}%`,
                        'width': `${maxPosition - minPosition}%`
                    });
                }
            }

            updateInputs() {
                // نمایش با کاما (فقط برای نمایش)
                this.minInput.val(this.currentMin).trigger("input")
                this.maxInput.val(this.currentMax).trigger("input")

                // ذخیره مقدار واقعی در data attribute
                this.minInput.data('raw-value', this.currentMin)
                this.maxInput.data('raw-value', this.currentMax)
            }
        }
    }


    // مقداردهی اولیه اسلایدر
    if ($('.nader-wc-ajax-filters-price-slider').length) {
        window.priceSliderInstance = new PriceSlider('.nader-wc-ajax-filters-price-slider');
    }
});

