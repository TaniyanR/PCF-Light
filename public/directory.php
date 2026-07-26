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
$names = [];

try {
    $pdo = db();
    $table = $directory['table'];
    $column = $directory['column'];

    if (db_table_exists($table)) {
        $stmt = $pdo->prepare(
            'SELECT `' . $column . '` AS name
             FROM `' . $table . '`
             WHERE `' . $column . '` IS NOT NULL AND `' . $column . '` <> ""
             GROUP BY `' . $column . '`
             ORDER BY `' . $column . '` ASC
             LIMIT 20000'
        );
        $stmt->execute();
        $names = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $exception) {
    error_log('public/directory.php failed: ' . $exception->getMessage());
}

$kanaOrder = ['あ', 'か', 'さ', 'た', 'な', 'は', 'ま', 'や', 'ら', 'わ'];
$kanaGroups = array_fill_keys($kanaOrder, []);
$alphaGroups = [];
$otherNames = [];

foreach ($names as $row) {
    $name = trim((string)($row['name'] ?? ''));
    if ($name === '') {
        continue;
    }

    $first = mb_substr($name, 0, 1, 'UTF-8');
    $hiragana = mb_convert_kana($first, 'c', 'UTF-8');
    $kana = '';
    if (preg_match('/^[ぁ-お]/u', $hiragana)) { $kana = 'あ'; }
    elseif (preg_match('/^[か-ご]/u', $hiragana)) { $kana = 'か'; }
    elseif (preg_match('/^[さ-ぞ]/u', $hiragana)) { $kana = 'さ'; }
    elseif (preg_match('/^[た-ど]/u', $hiragana)) { $kana = 'た'; }
    elseif (preg_match('/^[な-の]/u', $hiragana)) { $kana = 'な'; }
    elseif (preg_match('/^[は-ぽ]/u', $hiragana)) { $kana = 'は'; }
    elseif (preg_match('/^[ま-も]/u', $hiragana)) { $kana = 'ま'; }
    elseif (preg_match('/^[や-よ]/u', $hiragana)) { $kana = 'や'; }
    elseif (preg_match('/^[ら-ろ]/u', $hiragana)) { $kana = 'ら'; }
    elseif (preg_match('/^[わ-ん]/u', $hiragana)) { $kana = 'わ'; }

    if ($kana !== '') {
        $kanaGroups[$kana][] = $name;
    } elseif (preg_match('/^[A-Za-z]/', $first)) {
        $alphaGroups[strtoupper($first)][] = $name;
    } else {
        $otherNames[] = $name;
    }
}
ksort($alphaGroups);

$title = $directory['title'];
$pageDescription = $directory['title'] . 'です。名前を選ぶと該当商品の検索結果を表示します。';
$canonicalUrl = public_url('directory.php') . '?' . http_build_query(['type' => $type]);

require __DIR__ . '/partials/header.php';
?>
<?php pcf_render_hero($directory['title'], '名前を選ぶと商品の検索結果を表示します。'); ?>

<?php if ($names === []): ?>
  <?php pcf_render_empty('表示できるデータがありません。商品APIの同期後に自動で追加されます。'); ?>
<?php else: ?>
  <nav class="pcf-index-nav" aria-label="一覧内メニュー">
    <?php foreach ($kanaGroups as $kana => $groupNames): ?>
      <?php if ($groupNames !== []): ?>
        <a class="pcf-index-nav__item" href="#index-<?= e($kana) ?>"><?= e($kana) ?>行</a>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($alphaGroups !== []): ?>
      <a class="pcf-index-nav__item" href="#index-alpha">A〜Z</a>
    <?php endif; ?>
    <?php if ($otherNames !== []): ?>
      <a class="pcf-index-nav__item" href="#index-other">その他</a>
    <?php endif; ?>
  </nav>

  <div class="pcf-kana-directory">
    <?php foreach ($kanaGroups as $kana => $groupNames): ?>
      <?php if ($groupNames === []): continue; endif; ?>
      <section class="pcf-index-block" id="index-<?= e($kana) ?>" style="content-visibility:auto;contain-intrinsic-size:500px;">
        <h2 class="pcf-section-title"><?= e($kana) ?>行</h2>
        <div class="pcf-list-card__meta pcf-chip-list">
          <?php foreach ($groupNames as $name): ?>
            <a class="pcf-chip" href="<?= e(public_url('search.php') . '?' . http_build_query(['q' => $name, 'type' => $type])) ?>"><?= e($name) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <?php if ($alphaGroups !== []): ?>
      <section class="pcf-index-block" id="index-alpha" style="content-visibility:auto;contain-intrinsic-size:700px;">
        <h2 class="pcf-section-title">A〜Z</h2>
        <?php foreach ($alphaGroups as $letter => $groupNames): ?>
          <div class="pcf-list-card__meta pcf-chip-list">
            <strong><?= e($letter) ?></strong>
            <?php foreach ($groupNames as $name): ?>
              <a class="pcf-chip" href="<?= e(public_url('search.php') . '?' . http_build_query(['q' => $name, 'type' => $type])) ?>"><?= e($name) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ($otherNames !== []): ?>
      <section class="pcf-index-block" id="index-other" style="content-visibility:auto;contain-intrinsic-size:500px;">
        <h2 class="pcf-section-title">その他</h2>
        <div class="pcf-list-card__meta pcf-chip-list">
          <?php foreach ($otherNames as $name): ?>
            <a class="pcf-chip" href="<?= e(public_url('search.php') . '?' . http_build_query(['q' => $name, 'type' => $type])) ?>"><?= e($name) ?></a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
