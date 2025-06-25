<!-- overview section start -->
<section class="overview-section sp_separator_bg sp_pt_120 sp_pb_120">
    <div class="sp_container">
        <div class="row gy-4 align-items-center">
            <div class="col-lg-6 text-lg-start text-center">
                <h2 class="sp_theme_top_title"><?= Config::colorText(optional($content)->title, optional($content)->color_text_for_title) ?></h2>

                <div class="row gy-4 mt-lg-4 mt-2">
                    @foreach ($element as $item)
                        <div class="col-xl-6 col-6">
                            <div class="sp_overview_item">
                                <div class="sp_overview_content">
                                    <div class="d-flex flex-wrap align-items-center justify-content-lg-start justify-content-center">
                                        <h4 class="sp_overview_amount odometer"
                                            data-odometer-final="{{ filter_var($item->content->counter, FILTER_SANITIZE_NUMBER_INT) }}">
                                        </h4>
                                        <h4 class="sp_overview_amount">
                                            {{ preg_replace('/[^a-zA-Z]+/', '', $item->content->counter) }}</h4>
                                    </div>
                                    <p class="sp_overview_caption">{{ Config::trans($item->content->title) }}</p> 
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-6">
                <img src="{{ Config::getFile('overview', $content->image_one) }}" alt="image">
            </div>
        </div>
    </div>
</section>
<!-- overview section end -->