<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/partials/public_ui.php';

$directoryTypes = [
    'actress' => ['title' => '女優一覧', 'table' => 'item_actresses', 'column' => 'actress_name'],
    'genre' => ['title' => 'ジャンル一覧', 'table' => 'item_genres', 'column' => 'genre_name'],
    'maker' => ['title' => 'メーカー一覧', 'table' => 'item_makers', 'column' => 'maker_name'],
    'label' => ['title' => 'レーベル一覧', 'table' => 'item_labels', 'column' => 'label_name'],
    'series' => ['title' => 'シリーズ一覧', 'table' => 'item_series', 'column' => 'series_name'],
];

$type = trim((string)($_GET['type'] ?? 'actress'));
if (!isset($directoryTypes[$type])) {
    http_response_code(404);
    $type = 'actress';
}

$directory = $directoryTypes[$type];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 200;
$names = [];
$total = 0;
$pages = 1;

try {
    $pdo = db();
    $table = $directory['table'];
    $column = $directory['column'];

    if (db_table_exists($table)) {
        $total = (int)$pdo->query(
            'SELECT COUNT(DISTINCT `' . $column . '`) FROM `' . $table . '` WHERE `' . $column . '` IS NOT NULL AND `' . $column . '` <> ""'
        )->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            'SELECT `' . $column . '` AS name
             FROM `' . $table . '`
             WHERE `' . $column . '` IS NOT NULL AND `' . $column . '` <> ""
             GROUP BY `' . $column . '`
             ORDER BY `' . $column . '` ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $names = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $exception) {
    error_log('public/directory.php failed: ' . $exception->getMessage());
}

$title = $directory['title'];
$pageDescription = $directory['title'] . 'です。名前を選ぶと該当商品の検索結果を表示します。';
$canonicalQuery = ['type' => $type];
if ($page > 1) {
    $canonicalQuery['page'] = $page;
}
$canonicalUrl = public_url('directory.php') . '?' . http_build_query($canonicalQuery);
if ($page > 1) {
    $relPrev = public_url('directory.php') . '?' . http_build_query(['type' => $type, 'page' => $page - 1]);
}
if ($page < $pages) {
    $relNext = public_url('directory.php') . '?' . http_build_query(['type' => $type, 'page' => $page + 1]);
}

require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero($directory['title'], '名前を選ぶと商品の検索結果を表示します。'); ?>

<?php if ($names === []): ?>
  <?php pcf_render_empty('表示できるデータがありません。商品APIの同期後に自動で追加されます。'); ?>
<?php else: ?>
  <section class="pcf-directory-grid">
    <?php foreach ($names as $row): ?>
      <?php $name = trim((string)($row['name'] ?? '')); ?>
      <?php if ($name !== ''): ?>
        <a class="pcf-directory-link" href="<?= e(public_url('search.php') . '?' . http_build_query(['q' => $name])) ?>"><?= e($name) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </section>

  <nav class="pcf-pagination" aria-label="ページネーション">
    <?php if ($page > 1): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('directory.php') . '?' . http_build_query(['type' => $type, 'page' => $page - 1])) ?>">前へ</a>
    <?php endif; ?>
    <span class="pcf-pagination__link is-current"><?= e((string)$page) ?> / <?= e((string)$pages) ?></span>
    <?php if ($page < $pages): ?>
      <a class="pcf-pagination__link" href="<?= e(public_url('directory.php') . '?' . http_build_query(['type' => $type, 'page' => $page + 1])) ?>">次へ</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
