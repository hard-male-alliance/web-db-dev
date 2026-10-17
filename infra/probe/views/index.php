<?php
/**
 * Server-rendered infrastructure status; contains no credentials.
 */
declare(strict_types=1);

use yii\helpers\Html;
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Team website infrastructure</title>
<style>
body { max-width: 48rem; margin: 4rem auto; padding: 0 1rem; font: 1.1rem/1.6 system-ui; }
code { background: #edf3f7; padding: .2rem .4rem; border-radius: .3rem; }
</style>
<h1>Local infrastructure is ready</h1>
<p>Browser &rarr; PHP &rarr; Yii2 &rarr; MySQL / InnoDB</p>
<ul>
    <li>PHP <code><?= Html::encode($php) ?></code></li>
    <li>Yii2 <code><?= Html::encode($yii) ?></code></li>
    <li>MySQL <code><?= Html::encode($mysql) ?></code></li>
    <li>Storage engine <code><?= Html::encode($engine) ?></code></li>
</ul>
<p>This verifies the environment. Team website features are not implemented yet.</p>
</html>
