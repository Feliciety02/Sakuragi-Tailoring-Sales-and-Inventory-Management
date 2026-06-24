<?php
/**
 * Sakuragi Design System — Component Render Helpers
 *
 * Usage: require_once __DIR__ . '/config/component_helpers.php';
 *
 * Every function returns HTML string. Echo/print where needed.
 */

// ── Page Header ────────────────────────────────────────────────
function renderPageHeader(string $title, string $description = '', string $lastUpdated = '', array $actions = []): string {
    ob_start();
?>
<div class="page-header">
  <div class="page-header-main">
    <div>
      <h1><?= htmlspecialchars($title) ?></h1>
      <?php if ($description): ?>
      <p class="page-description"><?= htmlspecialchars($description) ?></p>
      <?php endif; ?>
    </div>
    <?php if ($actions): ?>
    <div class="page-actions">
      <?php foreach ($actions as $a):
        $tag = $a['tag'] ?? 'a';
        $href = $tag === 'a' ? ($a['href'] ?? '#') : '';
        $class = 'dash-btn dash-btn-' . ($a['variant'] ?? 'primary') . ($a['size'] === 'sm' ? ' dash-btn-sm' : '');
        $onclick = $a['onclick'] ?? '';
      ?>
      <<?= $tag ?> href="<?= htmlspecialchars($href) ?>" class="<?= $class ?>" <?= $onclick ? 'onclick="'.htmlspecialchars($onclick).'"' : '' ?>>
        <?php if (!empty($a['icon'])): ?><i class="<?= htmlspecialchars($a['icon']) ?>"></i><?php endif; ?>
        <?= htmlspecialchars($a['label']) ?>
      </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($lastUpdated): ?>
  <div class="page-meta">
    <i class="fas fa-clock"></i> Last updated <?= htmlspecialchars($lastUpdated) ?>
  </div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── KPI Card ───────────────────────────────────────────────────
function renderKPICard(string $icon, string $label, string $value, string $trend = '', string $trendLabel = '', string $accent = ''): string {
    $accentClass = $accent ? "kpi-accent-{$accent}" : '';
    ob_start();
?>
<div class="kpi-card <?= $accentClass ?>">
  <div class="kpi-icon kpi-icon-bg-<?= $accent ?: 'blue' ?>"><i class="<?= htmlspecialchars($icon) ?>"></i></div>
  <div class="kpi-label"><?= htmlspecialchars($label) ?></div>
  <div class="kpi-value"><?= $value ?></div>
  <?php if ($trend || $trendLabel): ?>
  <div class="kpi-trend"><?= $trend ? "<span class=\"kpi-trend-arrow\">{$trend}</span> " : '' ?><?= htmlspecialchars($trendLabel) ?></div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── KPI Row (wrapper) ──────────────────────────────────────────
function renderKPIRow(array $cards): string {
    ob_start();
?>
<div class="kpi-row">
  <?php foreach ($cards as $c):
    echo renderKPICard($c['icon'], $c['label'], (string)$c['value'], $c['trend'] ?? '', $c['trendLabel'] ?? '', $c['accent'] ?? '');
  endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

// ── Status Badge ───────────────────────────────────────────────
function renderStatusBadge(string $text, string $variant = 'neutral', string $size = ''): string {
    $sizeClass = $size === 'sm' ? 'status-badge-sm' : '';
    ob_start();
?>
<span class="status-badge status-badge-<?= htmlspecialchars($variant) ?> <?= $sizeClass ?>"><?= htmlspecialchars($text) ?></span>
<?php
    return ob_get_clean();
}

// ── Empty State ────────────────────────────────────────────────
function renderEmptyState(string $icon, string $title, string $message = '', array $action = []): string {
    ob_start();
?>
<div class="empty-state">
  <span class="empty-state-icon"><i class="<?= htmlspecialchars($icon) ?>"></i></span>
  <strong class="empty-state-title"><?= htmlspecialchars($title) ?></strong>
  <?php if ($message): ?>
  <p class="empty-state-message"><?= htmlspecialchars($message) ?></p>
  <?php endif; ?>
  <?php if ($action): ?>
  <a href="<?= htmlspecialchars($action['href'] ?? '#') ?>" class="empty-state-action dash-btn dash-btn-primary<?= !empty($action['size']) && $action['size']==='sm' ? ' dash-btn-sm' : '' ?>">
    <?php if (!empty($action['icon'])): ?><i class="<?= htmlspecialchars($action['icon']) ?>"></i><?php endif; ?>
    <?= htmlspecialchars($action['label'] ?? 'Action') ?>
  </a>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── Data Table ─────────────────────────────────────────────────
function renderDataTable(string $id, array $columns, array $rows, array $options = []): string {
    $searchable = !empty($options['searchable']);
    $sortable = !empty($options['sortable']);
    $emptyMsg = $options['emptyMessage'] ?? 'No data found';
    $emptyIcon = $options['emptyIcon'] ?? 'fas fa-inbox';
    ob_start();
?>
<div class="data-table-wrapper" id="<?= htmlspecialchars($id) ?>-wrapper">
  <?php if ($searchable || !empty($options['actions'])): ?>
  <div class="data-table-toolbar">
    <?php if ($searchable): ?>
    <div class="search-bar">
      <i class="fas fa-search search-bar-icon"></i>
      <input type="text" class="search-bar-input" id="<?= htmlspecialchars($id) ?>-search" placeholder="<?= htmlspecialchars($options['searchPlaceholder'] ?? 'Search...') ?>" data-table="<?= htmlspecialchars($id) ?>">
    </div>
    <?php endif; ?>
    <?php if (!empty($options['actions'])): ?>
    <div class="data-table-actions">
      <?php foreach ($options['actions'] as $a): ?>
      <a href="<?= htmlspecialchars($a['href'] ?? '#') ?>" class="dash-btn dash-btn-<?= $a['variant'] ?? 'outline' ?> dash-btn-sm">
        <?php if (!empty($a['icon'])): ?><i class="<?= htmlspecialchars($a['icon']) ?>"></i><?php endif; ?>
        <?= htmlspecialchars($a['label']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="data-table-scroll">
    <table class="data-table" id="<?= htmlspecialchars($id) ?>" data-sortable="<?= $sortable ? 'true' : 'false' ?>">
      <thead>
        <tr>
          <?php foreach ($columns as $ci): ?>
          <th class="data-table-th" data-field="<?= htmlspecialchars($ci['field'] ?? '') ?>">
            <?= htmlspecialchars($ci['label']) ?>
            <?php if ($sortable): ?><i class="fas fa-sort sort-icon"></i><?php endif; ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($columns) ?>" class="data-table-empty">
          <div class="empty-state" style="border:none;padding:48px 16px">
            <span class="empty-state-icon"><i class="<?= $emptyIcon ?>"></i></span>
            <strong class="empty-state-title"><?= htmlspecialchars($emptyMsg) ?></strong>
          </div>
        </td></tr>
        <?php else: foreach ($rows as $r): ?>
        <tr class="data-table-row">
          <?php foreach ($columns as $ci):
            $field = $ci['field'] ?? null;
            $val = $field ? ($r[$field] ?? '') : '';
            $cellClass = $ci['cellClass'] ?? '';
            if ($ci['type'] ?? '' === 'badge'):
              $badgeText = is_array($val) ? ($val['text'] ?? '') : $val;
              $badgeVariant = is_array($val) ? ($val['variant'] ?? 'neutral') : ($ci['variant'] ?? 'neutral');
          ?>
          <td class="data-table-cell <?= $cellClass ?>"><?= renderStatusBadge($badgeText, $badgeVariant, 'sm') ?></td>
          <?php elseif (($ci['type'] ?? '') === 'actions'): ?>
          <td class="data-table-cell actions-cell <?= $cellClass ?>">
            <?php foreach ((array)$val as $btn): ?>
            <a href="<?= htmlspecialchars($btn['href'] ?? '#') ?>" class="dash-btn dash-btn-<?= $btn['variant'] ?? 'outline' ?> dash-btn-sm" title="<?= htmlspecialchars($btn['label'] ?? '') ?>">
              <?php if (!empty($btn['icon'])): ?><i class="<?= htmlspecialchars($btn['icon']) ?>"></i><?php endif; ?>
              <?= htmlspecialchars($btn['label'] ?? '') ?>
            </a>
            <?php endforeach; ?>
          </td>
          <?php else:
            $safe = !empty($ci['safeHtml']);
            if (is_array($val)):
              $flat = array_filter(array_map(fn($v) => is_string($v) ? $v : (is_array($v) ? ($v['label']??'') : ''), $val));
              $out = htmlspecialchars(implode(', ', $flat));
            else:
              $out = $safe ? $val : htmlspecialchars((string)$val);
            endif;
          ?>
          <td class="data-table-cell <?= $cellClass ?>"><?= $out ?></td>
          <?php endif; endforeach; ?>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($options['pagination'])): ?>
  <div class="data-table-footer">
    <span class="data-table-info">Showing <?= count($rows) ?> of <?= $options['total'] ?? count($rows) ?></span>
    <?php if (!empty($options['paginationLinks'])): ?>
    <div class="data-table-pagination"><?= $options['paginationLinks'] ?></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── Activity Feed ──────────────────────────────────────────────
function renderActivityFeed(array $items, array $options = []): string {
    $emptyMsg = $options['emptyMessage'] ?? 'No recent activity';
    $emptyIcon = $options['emptyIcon'] ?? 'fas fa-clock';
    $max = $options['max'] ?? 0;
    if ($max > 0) $items = array_slice($items, 0, $max);
    ob_start();
?>
<div class="activity-feed">
  <?php if (empty($items)): ?>
  <div class="empty-state" style="border:none;padding:32px 16px">
    <span class="empty-state-icon"><i class="<?= $emptyIcon ?>"></i></span>
    <strong class="empty-state-title"><?= htmlspecialchars($emptyMsg) ?></strong>
  </div>
  <?php else: foreach ($items as $it):
    $dotColor = $it['dotColor'] ?? 'var(--accent)';
    $link = $it['link'] ?? '';
  ?>
  <div class="activity-item">
    <span class="activity-dot" style="background:<?= htmlspecialchars($dotColor) ?>"></span>
    <div class="activity-content">
      <?php if ($link): ?><a href="<?= htmlspecialchars($link) ?>"><?php endif; ?>
      <strong><?= htmlspecialchars($it['title'] ?? '') ?></strong>
      <?php if ($link): ?></a><?php endif; ?>
      <?= htmlspecialchars($it['description'] ?? '') ?>
      <div class="activity-time">
        <?php if (!empty($it['author'])): ?>by <?= htmlspecialchars($it['author']) ?> · <?php endif; ?>
        <?php if (!empty($it['time'])): ?><?= htmlspecialchars($it['time']) ?><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>
  <?php if (!empty($options['viewAllLink'])): ?>
  <a href="<?= htmlspecialchars($options['viewAllLink']) ?>" class="activity-view-all">View All</a>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── Quick Actions ──────────────────────────────────────────────
function renderQuickActions(array $actions, string $title = 'Quick Actions'): string {
    if (empty($actions)) return '';
    ob_start();
?>
<div class="quick-actions">
  <?php if ($title): ?><div class="quick-actions-title"><?= htmlspecialchars($title) ?></div><?php endif; ?>
  <div class="quick-actions-grid">
    <?php foreach ($actions as $a):
      $tag = $a['tag'] ?? 'a';
      $href = $tag === 'a' ? ($a['href'] ?? '#') : '';
      $onclick = $a['onclick'] ?? '';
    ?>
    <<?= $tag ?> href="<?= htmlspecialchars($href) ?>" class="quick-action-btn" <?= $onclick ? 'onclick="'.htmlspecialchars($onclick).'"' : '' ?>>
      <?php if (!empty($a['icon'])): ?><div class="quick-action-icon"><i class="<?= htmlspecialchars($a['icon']) ?>"></i></div><?php endif; ?>
      <div class="quick-action-label"><?= htmlspecialchars($a['label']) ?></div>
      <?php if (!empty($a['description'])): ?><div class="quick-action-desc"><?= htmlspecialchars($a['description']) ?></div><?php endif; ?>
    </<?= $tag ?>>
    <?php endforeach; ?>
  </div>
</div>
<?php
    return ob_get_clean();
}

// ── Page Section ───────────────────────────────────────────────
function renderPageSection(string $title, string $body, string $icon = '', array $actions = [], string $sidebar = ''): string {
    ob_start();
?>
<div class="page-section">
  <div class="page-section-header">
    <h2><?= $icon ? '<i class="'.htmlspecialchars($icon).' page-section-icon"></i> ' : '' ?><?= htmlspecialchars($title) ?></h2>
    <?php if ($actions): ?>
    <div class="action-bar">
      <?php foreach ($actions as $a): ?>
      <a href="<?= htmlspecialchars($a['href'] ?? '#') ?>" class="dash-btn dash-btn-<?= $a['variant'] ?? 'outline' ?> dash-btn-sm">
        <?php if (!empty($a['icon'])): ?><i class="<?= htmlspecialchars($a['icon']) ?>"></i><?php endif; ?>
        <?= htmlspecialchars($a['label']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="page-section-body <?= $sidebar ? 'page-section-body-with-sidebar' : '' ?>">
    <div class="page-section-main"><?= $body ?></div>
    <?php if ($sidebar): ?>
    <div class="page-section-sidebar"><?= $sidebar ?></div>
    <?php endif; ?>
  </div>
</div>
<?php
    return ob_get_clean();
}

// ── Search Bar ─────────────────────────────────────────────────
function renderSearchBar(string $id, string $placeholder = 'Search...', array $filters = []): string {
    ob_start();
?>
<div class="search-bar">
  <i class="fas fa-search search-bar-icon"></i>
  <input type="text" class="search-bar-input" id="<?= htmlspecialchars($id) ?>" placeholder="<?= htmlspecialchars($placeholder) ?>">
  <?php if ($filters): ?>
  <div class="search-bar-filters">
    <?php foreach ($filters as $f): ?>
    <select class="search-bar-select" id="<?= htmlspecialchars($f['id'] ?? '') ?>">
      <?php foreach ($f['options'] ?? [] as $o): ?>
      <option value="<?= htmlspecialchars($o['value'] ?? '') ?>"><?= htmlspecialchars($o['label'] ?? '') ?></option>
      <?php endforeach; ?>
    </select>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── Filter Bar ─────────────────────────────────────────────────
function renderFilterBar(array $groups): string {
    if (empty($groups)) return '';
    ob_start();
?>
<div class="filter-bar">
  <?php foreach ($groups as $g): ?>
  <div class="filter-group">
    <?php if (!empty($g['label'])): ?>
    <span class="filter-group-label"><?= htmlspecialchars($g['label']) ?>:</span>
    <?php endif; ?>
    <div class="filter-group-options">
      <?php foreach ($g['options'] ?? [] as $o):
        $active = !empty($o['active']);
      ?>
      <button class="filter-btn <?= $active ? 'filter-btn-active' : '' ?>" data-filter="<?= htmlspecialchars($o['value'] ?? '') ?>" onclick="<?= htmlspecialchars($o['onclick'] ?? '') ?>">
        <?= htmlspecialchars($o['label']) ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

// ── Panel Card (sidebar widget) ────────────────────────────────
function renderPanelCard(string $title, string $body, string $icon = '', string $footerLink = '', string $footerLabel = ''): string {
    ob_start();
?>
<div class="panel-card">
  <h3 class="panel-card-title"><?= $icon ? '<i class="'.htmlspecialchars($icon).'"></i> ' : '' ?><?= htmlspecialchars($title) ?></h3>
  <div class="panel-card-body"><?= $body ?></div>
  <?php if ($footerLink): ?>
  <a href="<?= htmlspecialchars($footerLink) ?>" class="panel-card-link"><?= htmlspecialchars($footerLabel ?: 'View All') ?></a>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── Dashboard Two-Column Layout ────────────────────────────────
function renderTwoColumn(string $main, string $sidebar, string $mainClass = ''): string {
    ob_start();
?>
<div class="dash-two-col">
  <div class="dash-main-col <?= htmlspecialchars($mainClass) ?>"><?= $main ?></div>
  <div class="dash-side-col"><?= $sidebar ?></div>
</div>
<?php
    return ob_get_clean();
}

// ── Universal Dashboard Shell ──────────────────────────────────
function renderDashboardShell(string $header, string $metricsRow, string $mainWorkspace, string $secondaryInsights = ''): string {
    ob_start();
?>
<div class="dash-content">
  <?= $header ?>
  <?= $metricsRow ?>
  <?php if ($mainWorkspace): ?>
  <div class="dash-workspace">
    <?= $mainWorkspace ?>
  </div>
  <?php endif; ?>
  <?php if ($secondaryInsights): ?>
  <div class="dash-insights">
    <?= $secondaryInsights ?>
  </div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

// ── KPI icon background variants ───────────────────────────────
// Maps accent names to existing CSS classes (kpi-icon-bg-*)
// ── Workflow Timeline ──────────────────────────────────────────
function renderWorkflowTimeline(array $steps, string $currentStep = ''): string {
    ob_start();
?>
<div class="workflow-timeline">
  <?php
  $found = false;
  foreach ($steps as $i => $s):
    $label = $s['label'] ?? '';
    $active = $label === $currentStep;
    if ($active) $found = true;
    if ($active):
      $cls = 'wf-step active';
    elseif (!$found):
      $cls = 'wf-step done';
    else:
      $cls = 'wf-step';
    endif;
    $desc = $s['description'] ?? '';
  ?>
  <div class="<?= $cls ?>">
    <div class="wf-step-marker">
      <?php if ($active): ?><i class="fas fa-chevron-right"></i>
      <?php elseif (!$found): ?><i class="fas fa-check"></i>
      <?php else: ?><div class="wf-step-num"><?= $i + 1 ?></div>
      <?php endif; ?>
    </div>
    <div class="wf-step-content">
      <div class="wf-step-label"><?= htmlspecialchars($label) ?></div>
      <?php if ($desc): ?><div class="wf-step-desc"><?= htmlspecialchars($desc) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

function kpiAccentClass(string $accent): string {
    $map = [
        'red'     => 'kpi-icon-bg-pink',
        'blue'    => 'kpi-icon-bg-blue',
        'green'   => 'kpi-icon-bg-green',
        'amber'   => 'kpi-icon-bg-amber',
        'purple'  => 'kpi-icon-bg-purple',
        'cyan'    => 'kpi-icon-bg-cyan',
    ];
    return $map[$accent] ?? 'kpi-icon-bg-blue';
}
