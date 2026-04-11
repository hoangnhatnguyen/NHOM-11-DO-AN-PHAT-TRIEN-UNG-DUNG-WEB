<?php
/**
 * @var int $paginationPage
 * @var int $paginationTotalPages
 * @var string $paginationBaseUrl path e.g. /admin/users
 * @var array<string, scalar|null> $paginationQuery e.g. ['q' => 'x'] — page is added per link
 */
$paginationPage = max(1, (int) ($paginationPage ?? 1));
$paginationTotalPages = max(1, (int) ($paginationTotalPages ?? 1));
$paginationBaseUrl = (string) ($paginationBaseUrl ?? '');
$paginationQuery = is_array($paginationQuery ?? null) ? $paginationQuery : [];
if ($paginationTotalPages <= 1) {
	return;
}
$buildUrl = static function (int $p) use ($paginationBaseUrl, $paginationQuery): string {
	$q = $paginationQuery;
	$q['page'] = $p;
	$qs = http_build_query(array_filter($q, static function ($v) {
		return $v !== null && $v !== '';
	}));
	$base = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/');
	$path = $paginationBaseUrl;
	if ($path !== '' && $path[0] !== '/') {
		$path = '/' . $path;
	}
	return $base . $path . ($qs !== '' ? '?' . $qs : '');
};
?>
<nav class="mt-3" aria-label="Phân trang">
	<ul class="pagination pagination-sm justify-content-center flex-wrap mb-0 admin-pagination">
		<li class="page-item <?= $paginationPage <= 1 ? 'disabled' : '' ?>">
			<a class="page-link rounded-pill" href="<?= $paginationPage <= 1 ? '#' : htmlspecialchars($buildUrl($paginationPage - 1), ENT_QUOTES, 'UTF-8') ?>">Trước</a>
		</li>
		<?php
		$from = max(1, $paginationPage - 2);
		$to = min($paginationTotalPages, $paginationPage + 2);
		for ($i = $from; $i <= $to; $i++):
		?>
			<li class="page-item <?= $i === $paginationPage ? 'active' : '' ?>">
				<a class="page-link rounded-pill" href="<?= htmlspecialchars($buildUrl($i), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
			</li>
		<?php endfor; ?>
		<li class="page-item <?= $paginationPage >= $paginationTotalPages ? 'disabled' : '' ?>">
			<a class="page-link rounded-pill" href="<?= $paginationPage >= $paginationTotalPages ? '#' : htmlspecialchars($buildUrl($paginationPage + 1), ENT_QUOTES, 'UTF-8') ?>">Sau</a>
		</li>
	</ul>
</nav>
