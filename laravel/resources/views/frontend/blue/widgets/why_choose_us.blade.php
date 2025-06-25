<section class="choose-section sp_pt_120 sp_pb_120">
    <div class="sp_container">
        <div class="row">
            <div class="col-lg-8 text-text-sm-start text-center">
                <div class="sp_theme_top  wow fadeInUp" data-wow-duration="0.3s" data-wow-delay="0.3s">
                    <h2 class="sp_theme_top_title"><?= Config::colorText(optional($content)->title, optional($content)->color_text_for_title) ?></h2>
                </div>
            </div>
        </div>
        <div class="row g-xl-4 gy-4 items-wrapper">
            @foreach ($element as $el)
                <div class="col-xxl-3 col-md-6 wow fadeInUp" data-wow-duration="0.3s">
                    <div class="sp_choose_item">
                        <div class="sp_choose_icon">
                            <img src="{{ Config::getFile('why_choose_us', $el->content->image_one) }}" alt="image">
                        </div>
                        <div class="sp_choose_content">
                            <h4 class="title">{{ Config::trans($el->content->title) }}</h4>
                            <p class="mt-2">{{ Config::trans($el->content->description) }}</p>
                        </div>
                    </div><!-- choose-item end -->
                </div>
            @endforeach
        </div>
    </div>
</section>