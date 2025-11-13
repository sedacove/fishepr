<?php
/** @var string $content */
/** @var string|null $pageTitle */

$page_title = $pageTitle ?? 'FisherP';

require_once __DIR__ . '/../../../includes/header.php';

echo $content ?? '';

require_once __DIR__ . '/../../../includes/footer.php';

