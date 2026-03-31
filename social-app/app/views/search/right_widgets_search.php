<?php 
$q   = $_GET['q'] ?? '';
$tab = $_GET['tab'] ?? 'top';

if ($tab === 'users') {
    $tab = 'people';
}
?>

<style>
.filter-date {
    width: 100%;
    min-width: 0;
    font-size: 12px;
    padding: 6px 8px;
}

.filter-date-wrap {
    display: flex;
    gap: 8px;
}

.filter-date-wrap input {
    flex: 1;
}

.form-check-input:checked {
    background-color: var(--brand-primary);
    border-color: var(--brand-primary);
}

.filter-group .form-check {
    margin-bottom: 10px;
}

.filter-group .form-check-label {
    margin-left: 6px;
}

.filter-group {
    line-height: 1.6;
}
</style>


<aside class="d-flex flex-column gap-3 right-sticky">

    <!-- SEARCH INPUT -->
    <div class="position-relative">
        <i class="bi bi-search position-absolute top-50 translate-middle-y ms-3 text-secondary"></i>
        <input class="form-control rounded-pill ps-5 border-0 shadow-sm" 
               placeholder="Tìm kiếm..." disabled>
    </div>

    <?php if ($q): ?>

        <!-- ===== FILTER ===== -->
        <section class="card border-primary-subtle rounded-4 shadow-sm">
            <div class="card-body p-3">

                <h6 class="fw-bold text-primary mb-3">Bộ lọc tìm kiếm</h6>

                <!-- 🔥 FORM -->
                <form id="searchFilterForm">

                    <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">

                    <?php if ($tab === 'people'): ?>

                        <!-- ===== PEOPLE FILTER ===== -->
                        <div class="filter-group">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filter_user" value="all"
                                <?= $filterUser === 'all' ? 'checked' : '' ?>>
                                <label class="form-check-label">Tất cả mọi người</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filter_user" value="following"
                                <?= $filterUser === 'following' ? 'checked' : '' ?>>
                                <label class="form-check-label">Bạn đang theo dõi</label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="filter_user" value="friends"
                                <?= $filterUser === 'friends' ? 'checked' : '' ?>>
                                <label class="form-check-label">Từ những người bạn đang theo dõi</label>
                            </div>

                        </div>

                    <?php else: ?>

                        <!-- ===== POST FILTER ===== -->
                        <div class="filter-group">

                            <div class="small fw-semibold mb-2">Mọi người</div>
                                 <div class="form-check">
                                    <input class="form-check-input" type="radio" name="filter_source" value="all"
                                        <?= $filterSource === 'all' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Từ bất kỳ ai</label>
                                 </div>
                                 <div class="form-check">
                                    <input class="form-check-input" type="radio" name="filter_source" value="following"
                                        <?= $filterSource === 'following' ? 'checked' : '' ?>>
                                    <label class="form-check-label">Từ những người bạn đang theo dõi</label>
                                 </div>

                            <!-- DATE -->
                            <div class="small fw-semibold mt-3 mb-2">Thời gian</div>

                            <div class="filter-date">
                                <input type="date" name="from" value="<?= $from ?? '' ?>">
                                <input type="date" name="to" value="<?= $to ?? '' ?>">
                            </div>

                        </div>

                    <?php endif; ?>

                    <!-- BUTTON -->
                    <button type="submit" class="btn btn-brand w-100 mt-2">
                        Áp dụng
                    </button>

                </form>

            </div>
        </section>

        <!-- ===== TRENDING ===== -->
        <section class="card border-primary-subtle rounded-4 shadow-sm">
            <div class="card-body p-3">
                <h6 class="fw-bold text-primary mb-3">Đang phổ biến</h6> 
                <div id="right-trending"></div>
            </div>
        </section>

    <?php endif; ?>


    <!-- ===== SUGGEST ===== -->
    <section class="card border-primary-subtle rounded-4 shadow-sm">
    <div class="card-body p-3">
        <h6 class="fw-bold text-primary mb-3">Gợi ý theo dõi</h6>

        <ul id="suggestBox" class="list-unstyled mb-0 small"></ul>

    </div>
    </section>

</aside>