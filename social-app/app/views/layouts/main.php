<!doctype html>
<html lang="vi">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title ?? 'Social App') ?></title>
	<style>
		body { font-family: Arial, sans-serif; margin: 0; background: #f6f7fb; color: #222; }
		.container { max-width: 980px; margin: 24px auto; padding: 0 16px; }
		.card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
		.muted { color: #6b7280; }
	</style>
</head>
<body>
	<main class="container">
		<?php include $contentView; ?>
	</main>
</body>
</html>

