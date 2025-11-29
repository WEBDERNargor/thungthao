<?php
$layout->setLayout('layout2');
$config = getConfig();
// Use useParams() so this works with both Router and vanilla build
$params = function_exists('useParams') ? useParams() : [];
$name=$params['name'] ?? null;
$id = $params['id'] ?? null;
$setHead(<<<HTML
<title> show id: {$id} - {$name} - {$config['web']['name']}</title>
HTML);
?>
<?= $id ?> - <?= $name ?>