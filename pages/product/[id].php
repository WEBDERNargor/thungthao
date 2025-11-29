<?php
$layout->setLayout('layout2');
$config = getConfig();
// Use useParams() so this works with both Router and vanilla build
$params = function_exists('useParams') ? useParams() : [];
$id = $params['id'] ?? null;
$setHead(<<<HTML
<title> show id: {$id} - {$config['web']['name']}</title>
HTML);
?>
<?= $id ?>