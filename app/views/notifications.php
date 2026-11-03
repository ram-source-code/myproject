<div class="container narrow-page" data-notif-center>
    <div class="page-head">
        <h1 class="page-title"><?= e(t('notifications')) ?></h1>
        <p class="muted"><?= e(t('notif_center_sub')) ?></p>
    </div>

    <div class="alert alert-warning" data-push-unsupported hidden><svg aria-hidden="true"><use href="#i-alert"></use></svg><p data-push-unsupported-text><?= e(t('push_unsupported')) ?></p></div>

    <section class="panel" aria-labelledby="news-h">
        <div class="toggle-row">
            <div>
                <h2 id="news-h" class="panel-title"><?= e(t('site_news')) ?></h2>
                <p class="muted small"><?= e(t('site_news_desc')) ?></p>
            </div>
            <label class="switch">
                <input type="checkbox" role="switch" data-news-toggle>
                <span class="visually-hidden"><?= e(t('site_news')) ?></span>
            </label>
        </div>
    </section>

    <section class="panel" aria-labelledby="fo-h">
        <h2 id="fo-h" class="panel-title"><?= e(t('offices_you_follow')) ?></h2>
        <ul class="follow-list" data-follow-list aria-live="polite"><li class="visually-hidden"><?= e(t('loading')) ?></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li></ul>
        <a class="btn btn-outline btn-sm" href="<?= e(url('offices')) ?>"><svg aria-hidden="true"><use href="#i-building"></use></svg><?= e(t('find_offices')) ?></a>
    </section>

    <section class="panel" aria-labelledby="ss-h">
        <h2 id="ss-h" class="panel-title"><?= e(t('saved_searches')) ?></h2>
        <p class="muted small"><?= e(t('saved_searches_desc')) ?></p>
        <ul class="follow-list" data-search-list aria-live="polite"><li class="visually-hidden"><?= e(t('loading')) ?></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li></ul>
        <a class="btn btn-outline btn-sm" href="<?= e(url('properties')) ?>"><svg aria-hidden="true"><use href="#i-search"></use></svg><?= e(t('new_search')) ?></a>
    </section>

    <section class="panel" aria-labelledby="feed-h">
        <h2 id="feed-h" class="panel-title"><?= e(t('recent_notifications')) ?></h2>
        <ul class="notif-list" data-feed aria-live="polite"><li class="visually-hidden"><?= e(t('loading')) ?></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li><li class="sk-row" aria-hidden="true"><span class="sk sk-dot"></span><span class="sk sk-line"></span></li></ul>
    </section>
</div>
