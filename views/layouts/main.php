<?php
use app\assets\AppAsset;
use yii\bootstrap5\Html;

AppAsset::register($this);
$this->registerCsrfMetaTags();

// Hide chrome on /auth/login
$hideSidebar = Yii::$app->controller->id === 'auth'
            && Yii::$app->controller->action->id === 'login';
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <title>LayInvest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?php $this->head() ?>
    <?php $this->registerCssFile('@web/css/sidebar.css'); ?>
    <?php
    $this->registerJsFile('@web/js/main.js', [
        'depends' => [\yii\web\JqueryAsset::class]
    ]);
    ?>
</head>

<body class="<?= $hideSidebar ? 'login-page' : '' ?>">
<?php $this->beginBody() ?>

<?php if (!$hideSidebar): ?>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="brand">
            <img src="<?= Yii::getAlias('@web/assets/images/sidebarLogo.png') ?>" alt="LayInvest Logo" style="width:125px; height:50px; border-radius:10px;">
            <a href="<?= \yii\helpers\Url::to(['dashboard/index']) ?>" class="brand-link">
                <!-- <span class="brand-text">LayInvest</span> -->
            </a>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
            <?= Html::a(
                Html::img(Yii::getAlias('@web/assets/images/dashboard.png'), [
                    'alt' => 'Overview',
                    'style' => 'width:23px; height:23px; margin-right:6px; vertical-align:middle;'
                ]) . ' Overview',
                ['dashboard/index'],
                [
                    'class' => 'nav-link ' . (Yii::$app->controller->id === 'dashboard' ? 'active' : '')
                ]
            ) ?>
            </li>
            <li class="nav-item">
                <?= Html::a(
                    Html::img(Yii::getAlias('@web/assets/images/cost-management.png'), [
                        'alt' => 'Cost Management',
                        'style' => 'width:24px; height:24px; margin-right:6px; vertical-align:middle;'
                    ]) . ' Operational Cost Analysis',
                    ['operational-cost/index'],
                    [
                        'class' => 'nav-link ' . (Yii::$app->controller->id === 'operational-cost' ? 'active' : '')
                    ]
                ) ?>
            </li>
            <li class="nav-item">
                <?= Html::a(
                    Html::img(Yii::getAlias('@web/assets/images/money.png'), [
                        'alt' => 'Revenue & Break-even',
                        'style' => 'width:24px; height:24px; margin-right:6px; vertical-align:middle;'
                    ]) . ' Revenue & Break-even Analysis',
                    ['revenue/view'],
                    [
                        'class' => 'nav-link ' . (Yii::$app->controller->id === 'revenue' ? 'active' : '')
                    ]
                ) ?>
            </li>
            <li class="nav-item">
                <?= Html::a(
                    Html::img(Yii::getAlias('@web/assets/images/check-list.png'), [
                        'alt' => 'Weekly Summary',
                        'style' => 'width:24px; height:24px; margin-right:6px; vertical-align:middle;'
                    ]) . ' Weekly Summary',
                    ['summary/index'],
                    [
                        'class' => 'nav-link ' . (Yii::$app->controller->id === 'summary' ? 'active' : '')
                    ]
                ) ?>
            </li>
        </ul>

        <!-- Footer (sticks to bottom) -->
        <div class="sidebar-footer">
            <?= Html::a('<i class="fas fa-sign-out-alt"></i> Logout', ['auth/logout'], [
                'class' => 'nav-link logout-link',
                'data-method' => 'post',
                'data-pjax' => 0,
            ]) ?>
        </div>
    </div>
<?php endif; ?>

<!-- Main content -->
<div class="main-content<?= $hideSidebar ? ' no-sidebar' : '' ?>" id="mainContent">
    <?php if (!$hideSidebar): ?>
        <header>
            <h2><?= Html::encode($this->title ?: 'Overview') ?></h2>
        </header>
    <?php endif; ?>

    <div>
        <?= $content ?>
    </div>
</div>

<script>
  (function () {
    var hb = document.getElementById('hamburger');
    if (hb) {
      hb.addEventListener('click', function () {
        var s = document.getElementById('sidebar');
        var m = document.getElementById('mainContent');
        if (s) s.classList.toggle('collapsed');
        if (m) m.classList.toggle('expanded');
      });
    }
  })();
</script>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
