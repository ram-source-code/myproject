<div class="container narrow-page legal">
    <div class="page-head">
        <h1 class="page-title"><?= e($heading) ?></h1>
    </div>
    <article class="panel prose legal-body">
        <?= render_partial('legal/' . $doc . '-' . lang(), ['updated' => $updated, 'email' => $email]) ?>
    </article>
</div>
