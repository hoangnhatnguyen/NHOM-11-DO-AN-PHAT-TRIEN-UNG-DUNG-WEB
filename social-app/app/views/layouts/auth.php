<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? APP_NAME) ?></title>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/public/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/public/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/public/images/favicon-16x16.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/public/images/favicon-32x32.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="<?= BASE_URL ?>/public/css/auth.css" rel="stylesheet">
</head>
<body class="auth-page-bg">
<main class="container-fluid py-4 py-md-5">
    <?php include $contentView; ?>
</main>
</body>
</html>
