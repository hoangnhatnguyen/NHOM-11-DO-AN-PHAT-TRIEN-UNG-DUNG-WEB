<?php
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$tab = $_GET['tab'] ?? 'top';
$filterUser = $_GET['filter_user'] ?? 'all';
$filterSource = $_GET['filter_source'] ?? 'all';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
?>

<style>


.search-box {
    background: #f5f7fa;
    border-radius: 999px;
    padding: 10px 15px;
    border: 1px solid #ddd;
    display: flex;
    align-items: center;
}

.search-box input {
    border: none;
    outline: none;
    background: transparent;
    width: 100%;
    flex: 1;
}

/* ===== TAB ===== */
.tabs {
    display: flex;
    justify-content: space-around;
    border-bottom: 1px solid #eee;
    margin-top: 10px;
}

.tab {
    flex: 1;
    text-align: center;
    padding: 12px 0;
    cursor: pointer;
    color: #666;
    position: relative;
}

.tab.active {
    color: #1A6291;
    font-weight: 600;
}

.tab.active::after {
    content: "";
    position: absolute;
    bottom: -1px;
    left: 20%;
    right: 20%;
    height: 2px;
    background: #1A6291;
}

/* FIX NỀN */
.feed-layout,
.col-md-7,
.col-lg-6 {
    background: transparent !important;
}

/* ===== MINI CARD ===== */
.mini-card {
    background: #fff;
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 10px;
    border: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
}

.mini-card:hover {
    background: #f9fafb;
    transform: translateY(-1px);
}

/* icon */
.mini-icon {
    font-size: 18px;
}

/* text */
.mini-text {
    font-weight: 500;
}



</style>


<div class="row g-3 g-lg-4 feed-layout px-lg-4">

  <!-- LEFT -->
  <div class="col-12 col-md-2 col-lg-3">
    <?php include VIEW_PATH . 'partials/feed/left_sidebar.php'; ?>
  </div>

  <!-- CENTER -->
  <div class="col-12 col-md-7 col-lg-6">

    <!-- ===== SEARCH + TAB (CARD NHỎ) ===== -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-body">

        <form method="GET" action="/search">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input 
                    name="q"
                    placeholder="Tìm kiếm..."
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                >
            </div>
        </form>

        <?php if (!$q): ?>
          <div class="tabs">
            <div class="tab active" data-tab="recent">Gần đây</div>
            <div class="tab" data-tab="trending">Đang phổ biến</div>
          </div>
        <?php else: ?>
          <div class="tabs">
            <div class="tab <?= $tab=='top'?'active':'' ?>" data-tab="top">Hàng đầu</div>
            <div class="tab <?= $tab=='latest'?'active':'' ?>" data-tab="latest">Mới nhất</div>
            <div class="tab <?= $tab=='users'?'active':'' ?>" data-tab="users">Mọi người</div>
          </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- ===== CONTENT ===== -->

    <?php if (!$q): ?>

      <div id="default-content"></div>

    <?php else: ?>

      <!-- ===== TOP ===== -->
      <?php if ($tab === 'top'): ?>

        <?php if (!empty($users)): ?>
          <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body">

              <div class="fw-semibold mb-2">Mọi người</div>

              <?php foreach ($users as $u): ?>
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-secondary" style="width:40px;height:40px;"></div>
                    <div class="fw-semibold">
                      <?= htmlspecialchars($u['username']) ?>
                    </div>
                  </div>

                  <button class="btn btn-sm rounded-pill btn-follow">Theo dõi</button>
                </div>
              <?php endforeach; ?>

              <a href="?q=<?= urlencode($q) ?>&tab=users" class="small text-primary">
                Xem tất cả
              </a>

            </div>
          </div>
        <?php endif; ?>

        <div>
          <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
              <?php include VIEW_PATH . 'partials/post_card.php'; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-muted mt-2">Không có bài viết</div>
          <?php endif; ?>
        </div>

      <?php endif; ?>

      <!-- ===== LATEST ===== -->
      <?php if ($tab === 'latest'): ?>
        <div>
          <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
              <?php include VIEW_PATH . 'partials/post_card.php'; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-muted mt-2">Không có bài viết</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- ===== USERS ===== -->
      <?php if ($tab === 'users'): ?>
        <div class="card border-0 shadow-sm rounded-4">
          <div class="card-body">

            <?php foreach ($users as $u): ?>
              <div class="d-flex align-items-center justify-content-between mb-3">

                <div class="d-flex align-items-center gap-3">
                  <div class="rounded-circle bg-secondary" style="width:40px;height:40px;"></div>
                  <div class="fw-semibold">
                    <?= htmlspecialchars($u['username']) ?>
                  </div>
                </div>

            
                <button class="btn btn-sm rounded-pill btn-follow">Theo dõi</button>
              </div>
            <?php endforeach; ?>

          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>

  </div>

  <!-- RIGHT -->
  <div class="col-12 col-md-3 col-lg-3">
    <?php include __DIR__ . '/right_widgets_search.php'; ?>
  </div>

</div>