<?php
use app\assets\AppAsset;
use yii\bootstrap5\Html;

AppAsset::register($this);
$this->registerCsrfMetaTags();
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
<body>
    
<?php $this->beginBody() ?>

<div class="sidebar" id="sidebar">
    <div class="brand">
        <i class="fas fa-egg"></i>
        <span class="brand-text">LayInvest</span>
    </div>
    <ul>
        <ul>
    <li><?= Html::a('<i class="fas fa-chart-pie"></i> Overview', ['dashboard/index']) ?></li>
    <li><?= Html::a('<i class="fas fa-chart-line"></i> Operational Cost Analysis', ['operational-cost/create']) ?></li>
    <li>
    <?= Html::a(
        '<i class="fas fa-dollar-sign"></i> Revenue & Break-even Analysis',
        ['revenue/view'],
        ['class' => 'nav-link ' . (Yii::$app->controller->id === 'revenue' ? 'active' : '')]
    ) ?>
    </li>
    <li><?= Html::a('<i class="fas fa-list"></i> Summary', ['summary/index']) ?></li>
        </ul>
    </ul>
</div>

<div class="main-content" id="mainContent">
    <header>
        <i class="fas fa-bars hamburger" id="hamburger"></i>
        <h2><?= Html::encode($this->title ?: 'LayInvest Dashboard') ?></h2>
    </header>
    <div>
        <?= $content ?>
    </div>
</div>

<script>
    document.getElementById('hamburger').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
        document.getElementById('mainContent').classList.toggle('expanded');
    });
</script>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
