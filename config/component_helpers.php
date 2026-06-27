<?php
/**
 * Shared dashboard component render helpers.
 */

function renderPageHeader(string $title, string $description = '', string $lastUpdated = '', array $actions = []): string
{
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
      <?php foreach ($actions as $action):
          $tag = $action['tag'] ?? 'a';
          $variant = $action['variant'] ?? 'primary';
          $class = 'dash-btn dash-btn-' . $variant . ((($action['size'] ?? '') === 'sm') ? ' dash-btn-sm' : '');
          $onclick = $action['onclick'] ?? '';
          $titleAttr = !empty($action['title']) ? ' title="' . htmlspecialchars($action['title']) . '"' : '';
      ?>
      <?php if ($tag === 'button'): ?>
      <button type="<?= htmlspecialchars($action['buttonType'] ?? 'button') ?>" class="<?= htmlspecialchars($class) ?>"<?= $onclick ? ' onclick="' . htmlspecialchars($onclick) . '"' : '' ?><?= $titleAttr ?>>
        <?php if (!empty($action['icon'])): ?><i class="<?= htmlspecialchars($action['icon']) ?>"></i><?php endif; ?>
        <?= htmlspecialchars($action['label'] ?? '') ?>
      </button>
      <?php else: ?>
      <a href="<?= htmlspecialchars($action['href'] ?? '#') ?>" class="<?= htmlspecialchars($class) ?>"<?= $onclick ? ' onclick="' . htmlspecialchars($onclick) . '"' : '' ?><?= $titleAttr ?>>
        <?php if (!empty($action['icon'])): ?><i class="<?= htmlspecialchars($action['icon']) ?>"></i><?php endif; ?>
        <?= htmlspecialchars($action['label'] ?? '') ?>
      </a>
      <?php endif; ?>
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

function renderKPICard(string $icon, string $label, string $value, string $trend = '', string $trendLabel = '', string $accent = ''): string
{
    $accentClass = $accent ? "kpi-accent-{$accent}" : '';

    ob_start();
    ?>
<div class="kpi-card <?= htmlspecialchars($accentClass) ?>">
  <div class="kpi-icon kpi-icon-bg-<?= htmlspecialchars($accent ?: 'blue') ?>"><i class="<?= htmlspecialchars($icon) ?>"></i></div>
  <div class="kpi-label"><?= htmlspecialchars($label) ?></div>
  <div class="kpi-value"><?= $value ?></div>
  <?php if ($trend || $trendLabel): ?>
  <div class="kpi-trend"><?= $trend ? '<span class="kpi-trend-arrow">' . htmlspecialchars($trend) . '</span> ' : '' ?><?= htmlspecialchars($trendLabel) ?></div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

function renderKPIRow(array $cards): string
{
    ob_start();
    ?>
<div class="kpi-row">
  <?php foreach ($cards as $card): ?>
    <?= renderKPICard($card['icon'], $card['label'], (string) $card['value'], $card['trend'] ?? '', $card['trendLabel'] ?? '', $card['accent'] ?? '') ?>
  <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

function renderStatusBadge(string $text, string $variant = 'neutral', string $size = ''): string
{
    $sizeClass = $size === 'sm' ? 'status-badge-sm' : '';

    ob_start();
    ?>
<span class="status-badge status-badge-<?= htmlspecialchars($variant) ?> <?= htmlspecialchars($sizeClass) ?>"><?= htmlspecialchars($text) ?></span>
<?php
    return ob_get_clean();
}

function renderEmptyState(string $icon, string $title, string $message = '', array $action = []): string
{
    ob_start();
    ?>
<div class="empty-state">
  <span class="empty-state-icon"><i class="<?= htmlspecialchars($icon) ?>"></i></span>
  <strong class="empty-state-title"><?= htmlspecialchars($title) ?></strong>
  <?php if ($message): ?>
  <p class="empty-state-message"><?= htmlspecialchars($message) ?></p>
  <?php endif; ?>
  <?php if ($action): ?>
  <?= renderTableToolbarAction(array_merge(['variant' => 'primary', 'size' => 'sm'], $action)) ?>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

function renderTableToolbarAction(array $action): string
{
    $tag = $action['tag'] ?? 'a';
    $variant = $action['variant'] ?? 'outline';
    $sizeClass = (($action['size'] ?? 'sm') === 'sm') ? ' dash-btn-sm' : '';
    $iconOnly = !empty($action['iconOnly']);
    $class = trim('dash-btn dash-btn-' . $variant . $sizeClass . ($iconOnly ? ' dash-btn-icon-only' : '') . ' ' . ($action['class'] ?? ''));
    $icon = !empty($action['icon']) ? '<i class="' . htmlspecialchars($action['icon']) . '"></i>' : '';
    $label = htmlspecialchars($action['label'] ?? 'Action');
    $title = $action['title'] ?? ($iconOnly ? ($action['label'] ?? 'Action') : '');
    $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title) . '"' : '';
    $ariaLabelAttr = $iconOnly ? ' aria-label="' . htmlspecialchars($action['label'] ?? 'Action') . '"' : '';
    $onclickAttr = !empty($action['onclick']) ? ' onclick="' . htmlspecialchars($action['onclick']) . '"' : '';
    $content = $icon . ($iconOnly ? '<span class="sr-only">' . $label . '</span>' : $label);

    if ($tag === 'button') {
        return '<button type="' . htmlspecialchars($action['buttonType'] ?? 'button') . '" class="' . htmlspecialchars($class) . '"' . $titleAttr . $ariaLabelAttr . $onclickAttr . '>' . $content . '</button>';
    }

    return '<a href="' . htmlspecialchars($action['href'] ?? '#') . '" class="' . htmlspecialchars($class) . '"' . $titleAttr . $ariaLabelAttr . $onclickAttr . '>' . $content . '</a>';
}

function tableCellValue(array $row, array $column, int $index)
{
    $field = $column['field'] ?? null;
    if ($field !== null && array_key_exists($field, $row)) {
        return $row[$field];
    }
    if (array_key_exists($index, $row)) {
        return $row[$index];
    }
    return null;
}

function renderTableCellContent($value, array $column): string
{
    $columnType = $column['type'] ?? 'text';
    if (is_array($value) && isset($value['type'])) {
        $columnType = $value['type'];
    } elseif (is_array($value) && isset($value[0]['type'])) {
        $columnType = $value[0]['type'];
    }

    if ($columnType === 'badge') {
        $text = is_array($value) ? (string) ($value['text'] ?? $value['label'] ?? '') : (string) $value;
        $variant = is_array($value) ? (string) ($value['variant'] ?? $column['variant'] ?? 'neutral') : (string) ($column['variant'] ?? 'neutral');
        return renderStatusBadge($text, $variant, 'sm');
    }

    if ($columnType === 'actions') {
        $actions = [];
        if (is_array($value) && isset($value['value']) && is_array($value['value'])) {
            $actions = $value['value'];
        } elseif (is_array($value) && isset($value[0]['type']) && ($value[0]['type'] ?? '') === 'actions' && isset($value[0]['value']) && is_array($value[0]['value'])) {
            $actions = $value[0]['value'];
        } elseif (is_array($value)) {
            $actions = $value;
        }

        $html = '';
        foreach ($actions as $action) {
            if (is_array($action)) {
                $html .= renderTableToolbarAction($action);
            }
        }
        return $html;
    }

    if ($columnType === 'link') {
        if (!is_array($value)) {
            return htmlspecialchars((string) $value);
        }

        $tag = $value['tag'] ?? 'a';
        $label = htmlspecialchars((string) ($value['label'] ?? $value['text'] ?? ''));
        $icon = !empty($value['icon']) ? '<i class="' . htmlspecialchars($value['icon']) . '"></i>' : '';
        $class = trim('table-link ' . ($value['class'] ?? ''));
        $titleAttr = !empty($value['title']) ? ' title="' . htmlspecialchars($value['title']) . '"' : '';
        $onclickAttr = !empty($value['onclick']) ? ' onclick="' . htmlspecialchars($value['onclick']) . '"' : '';

        if ($tag === 'button') {
            return '<button type="' . htmlspecialchars($value['buttonType'] ?? 'button') . '" class="' . htmlspecialchars($class) . '"' . $titleAttr . $onclickAttr . '>' . $icon . $label . '</button>';
        }

        return '<a href="' . htmlspecialchars($value['href'] ?? '#') . '" class="' . htmlspecialchars($class) . '"' . $titleAttr . $onclickAttr . '>' . $icon . $label . '</a>';
    }

    if (is_array($value) && isset($value['html'])) {
        return (string) $value['html'];
    }

    if (is_array($value) && isset($value['trustedHtml'])) {
        return (string) $value['trustedHtml'];
    }

    if (is_array($value) && isset($value['text'])) {
        return htmlspecialchars((string) $value['text']);
    }

    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $parts[] = (string) $item;
            } elseif (is_array($item)) {
                $parts[] = (string) ($item['label'] ?? $item['text'] ?? '');
            }
        }
        return htmlspecialchars(implode(', ', array_filter($parts, static fn($part) => $part !== '')));
    }

    if (!empty($column['safeHtml'])) {
        return (string) $value;
    }

    return htmlspecialchars((string) $value);
}

function renderDataTable(string $id, array $columns, array $rows, array $options = []): string
{
    $searchable = $options['searchable'] ?? true;
    $sortable = $options['sortable'] ?? true;
    $emptyMessage = $options['emptyMessage'] ?? 'No data found';
    $emptyIcon = $options['emptyIcon'] ?? 'fas fa-inbox';
    $pageSize = max(1, (int) ($options['pageSize'] ?? 10));
    $pageSizeOptions = $options['pageSizeOptions'] ?? [10, 25, 50, 100];
    $searchPlaceholder = $options['searchPlaceholder'] ?? 'Search table...';
    $filterableColumns = [];

    foreach ($columns as $index => $column) {
        $columnType = $column['type'] ?? 'text';
        $isFilterable = $column['filterable'] ?? ($columnType !== 'actions');
        if ($isFilterable) {
            $filterableColumns[] = [
                'index' => $index,
                'label' => $column['label'] ?? ('Column ' . ($index + 1)),
            ];
        }
    }

    ob_start();
    ?>
<div class="data-table-wrapper" id="<?= htmlspecialchars($id) ?>-wrapper">
  <div class="data-table-toolbar">
    <div class="data-table-toolbar-main">
      <?php if ($searchable): ?>
      <div class="search-bar">
        <i class="fas fa-search search-bar-icon"></i>
        <input type="text" class="search-bar-input" id="<?= htmlspecialchars($id) ?>-search" placeholder="<?= htmlspecialchars($searchPlaceholder) ?>" data-table="<?= htmlspecialchars($id) ?>">
      </div>
      <?php endif; ?>
      <div class="data-table-filters">
        <select class="search-bar-select dt-filter-column" data-table="<?= htmlspecialchars($id) ?>">
          <option value="">Filter column</option>
          <?php foreach ($filterableColumns as $filterColumn): ?>
          <option value="<?= (int) $filterColumn['index'] ?>"><?= htmlspecialchars($filterColumn['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="search-bar-select dt-filter-value" data-table="<?= htmlspecialchars($id) ?>" disabled>
          <option value="">All values</option>
        </select>
      </div>
    </div>
    <div class="data-table-actions">
      <?php foreach ($options['actions'] ?? [] as $action): ?>
        <?= renderTableToolbarAction($action) ?>
      <?php endforeach; ?>
      <button type="button" class="dash-btn dash-btn-outline dash-btn-sm dt-export-btn" data-export="csv" data-table="<?= htmlspecialchars($id) ?>"><i class="fas fa-file-csv"></i> CSV</button>
      <button type="button" class="dash-btn dash-btn-outline dash-btn-sm dt-export-btn" data-export="excel" data-table="<?= htmlspecialchars($id) ?>"><i class="fas fa-file-excel"></i> Excel</button>
    </div>
  </div>

  <div class="data-table-loading" aria-hidden="true">
    <i class="fas fa-spinner fa-spin"></i>
    <span>Loading table...</span>
  </div>

  <div class="data-table-scroll">
    <table class="data-table" id="<?= htmlspecialchars($id) ?>" data-sortable="<?= $sortable ? 'true' : 'false' ?>" data-empty-message="<?= htmlspecialchars($emptyMessage) ?>" data-empty-icon="<?= htmlspecialchars($emptyIcon) ?>" data-page-size="<?= (int) $pageSize ?>">
      <thead>
        <tr>
          <?php foreach ($columns as $index => $column): ?>
          <?php
          $field = $column['field'] ?? ('column_' . $index);
          $columnType = $column['type'] ?? 'text';
          $isSortable = ($column['sortable'] ?? true) && $columnType !== 'actions' && $sortable;
          ?>
          <th class="data-table-th<?= $isSortable ? ' is-sortable' : '' ?>" data-field="<?= htmlspecialchars($field) ?>" data-column-index="<?= (int) $index ?>" data-type="<?= htmlspecialchars($columnType) ?>">
            <span><?= htmlspecialchars($column['label'] ?? '') ?></span>
            <?php if ($isSortable): ?><i class="fas fa-sort sort-icon"></i><?php endif; ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr class="data-table-row">
          <?php foreach ($columns as $index => $column): ?>
          <?php
          $value = tableCellValue($row, $column, $index);
          $content = renderTableCellContent($value, $column);
          $sortValue = is_array($value)
              ? (string) ($value['sort'] ?? $value['text'] ?? $value['label'] ?? strip_tags($content))
              : strip_tags((string) $value);
          $filterValue = is_array($value)
              ? (string) ($value['filter'] ?? $value['text'] ?? $value['label'] ?? strip_tags($content))
              : strip_tags((string) $value);
          $isActionsCell = (($column['type'] ?? '') === 'actions') || (is_array($value) && (($value['type'] ?? '') === 'actions'));
          $cellClass = trim('data-table-cell ' . ($column['cellClass'] ?? '') . ($isActionsCell ? ' actions-cell' : ''));
          ?>
          <td class="<?= htmlspecialchars($cellClass) ?>" data-column-index="<?= (int) $index ?>" data-sort-value="<?= htmlspecialchars(trim($sortValue)) ?>" data-filter-value="<?= htmlspecialchars(trim($filterValue)) ?>"><?= $content ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="data-table-empty-state" hidden>
    <?= renderEmptyState($emptyIcon, $emptyMessage, $options['emptyDetail'] ?? '') ?>
  </div>

  <div class="data-table-footer">
    <div class="data-table-footer-left">
      <span class="dt-info">Showing 0 entries</span>
      <label class="dt-page-size">
        <span>Rows</span>
        <select class="search-bar-select dt-page-size-select">
          <?php foreach ($pageSizeOptions as $size): ?>
          <option value="<?= (int) $size ?>"<?= (int) $size === $pageSize ? ' selected' : '' ?>><?= (int) $size ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="dt-pagination"></div>
  </div>
</div>
<?php
    return ob_get_clean();
}

function renderActivityFeed(array $items, array $options = []): string
{
    $emptyMsg = $options['emptyMessage'] ?? 'No recent activity';
    $emptyIcon = $options['emptyIcon'] ?? 'fas fa-clock';
    $max = $options['max'] ?? 0;
    if ($max > 0) {
        $items = array_slice($items, 0, $max);
    }

    ob_start();
    ?>
<div class="activity-feed">
  <?php if (empty($items)): ?>
  <div class="empty-state" style="border:none;padding:32px 16px">
    <span class="empty-state-icon"><i class="<?= htmlspecialchars($emptyIcon) ?>"></i></span>
    <strong class="empty-state-title"><?= htmlspecialchars($emptyMsg) ?></strong>
  </div>
  <?php else: ?>
  <?php foreach ($items as $item): ?>
  <div class="activity-item">
    <span class="activity-dot" style="background:<?= htmlspecialchars($item['dotColor'] ?? 'var(--accent)') ?>"></span>
    <div class="activity-content">
      <?php if (!empty($item['link'])): ?><a href="<?= htmlspecialchars($item['link']) ?>"><?php endif; ?>
      <strong><?= htmlspecialchars($item['title'] ?? '') ?></strong>
      <?php if (!empty($item['link'])): ?></a><?php endif; ?>
      <?= htmlspecialchars($item['description'] ?? '') ?>
      <div class="activity-time">
        <?php if (!empty($item['author'])): ?>by <?= htmlspecialchars($item['author']) ?> · <?php endif; ?>
        <?php if (!empty($item['time'])): ?><?= htmlspecialchars($item['time']) ?><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <?php if (!empty($options['viewAllLink'])): ?>
  <a href="<?= htmlspecialchars($options['viewAllLink']) ?>" class="activity-view-all">View All</a>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

function renderQuickActions(array $actions, string $title = 'Quick Actions'): string
{
    if (empty($actions)) {
        return '';
    }

    ob_start();
    ?>
<div class="quick-actions">
  <?php if ($title): ?><div class="quick-actions-title"><?= htmlspecialchars($title) ?></div><?php endif; ?>
  <div class="quick-actions-grid">
    <?php foreach ($actions as $action): ?>
    <a href="<?= htmlspecialchars($action['href'] ?? '#') ?>" class="quick-action-btn"<?= !empty($action['onclick']) ? ' onclick="' . htmlspecialchars($action['onclick']) . '"' : '' ?>>
      <?php if (!empty($action['icon'])): ?><div class="quick-action-icon"><i class="<?= htmlspecialchars($action['icon']) ?>"></i></div><?php endif; ?>
      <div class="quick-action-label"><?= htmlspecialchars($action['label'] ?? '') ?></div>
      <?php if (!empty($action['description'])): ?><div class="quick-action-desc"><?= htmlspecialchars($action['description']) ?></div><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php
    return ob_get_clean();
}

function renderPageSection(string $title, string $body, string $icon = '', array $actions = [], string $sidebar = ''): string
{
    ob_start();
    ?>
<div class="page-section">
  <div class="page-section-header">
    <h2><?= $icon ? '<i class="' . htmlspecialchars($icon) . ' page-section-icon"></i> ' : '' ?><?= htmlspecialchars($title) ?></h2>
    <?php if ($actions): ?>
    <div class="action-bar">
      <?php foreach ($actions as $action): ?>
        <?= renderTableToolbarAction($action) ?>
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

function renderSearchBar(string $id, string $placeholder = 'Search...', array $filters = []): string
{
    ob_start();
    ?>
<div class="search-bar">
  <i class="fas fa-search search-bar-icon"></i>
  <input type="text" class="search-bar-input" id="<?= htmlspecialchars($id) ?>" placeholder="<?= htmlspecialchars($placeholder) ?>">
  <?php if ($filters): ?>
  <div class="search-bar-filters">
    <?php foreach ($filters as $filter): ?>
    <select class="search-bar-select" id="<?= htmlspecialchars($filter['id'] ?? '') ?>">
      <?php foreach ($filter['options'] ?? [] as $option): ?>
      <option value="<?= htmlspecialchars($option['value'] ?? '') ?>"><?= htmlspecialchars($option['label'] ?? '') ?></option>
      <?php endforeach; ?>
    </select>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

function renderFilterBar(array $groups): string
{
    if (empty($groups)) {
        return '';
    }

    ob_start();
    ?>
<div class="filter-bar">
  <?php foreach ($groups as $group): ?>
  <div class="filter-group">
    <?php if (!empty($group['label'])): ?><span class="filter-group-label"><?= htmlspecialchars($group['label']) ?>:</span><?php endif; ?>
    <div class="filter-group-options">
      <?php foreach ($group['options'] ?? [] as $option): ?>
      <button class="filter-btn<?= !empty($option['active']) ? ' filter-btn-active' : '' ?>" data-filter="<?= htmlspecialchars($option['value'] ?? '') ?>"<?= !empty($option['onclick']) ? ' onclick="' . htmlspecialchars($option['onclick']) . '"' : '' ?>>
        <?= htmlspecialchars($option['label'] ?? '') ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

function renderPanelCard(string $title, string $body, string $icon = '', string $footerLink = '', string $footerLabel = ''): string
{
    ob_start();
    ?>
<div class="panel-card">
  <h3 class="panel-card-title"><?= $icon ? '<i class="' . htmlspecialchars($icon) . '"></i> ' : '' ?><?= htmlspecialchars($title) ?></h3>
  <div class="panel-card-body"><?= $body ?></div>
  <?php if ($footerLink): ?>
  <a href="<?= htmlspecialchars($footerLink) ?>" class="panel-card-link"><?= htmlspecialchars($footerLabel ?: 'View All') ?></a>
  <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

function renderTwoColumn(string $main, string $sidebar, string $mainClass = ''): string
{
    ob_start();
    ?>
<div class="dash-two-col">
  <div class="dash-main-col <?= htmlspecialchars($mainClass) ?>"><?= $main ?></div>
  <div class="dash-side-col"><?= $sidebar ?></div>
</div>
<?php
    return ob_get_clean();
}

function renderDashboardShell(string $header, string $metricsRow, string $mainWorkspace, string $secondaryInsights = ''): string
{
    ob_start();
    ?>
<div class="dash-content">
  <?= $header ?>
  <?= $metricsRow ?>
  <?php if ($mainWorkspace): ?>
  <div class="dash-workspace"><?= $mainWorkspace ?></div>
  <?php endif; ?>
  <?php if ($secondaryInsights): ?>
  <div class="dash-insights"><?= $secondaryInsights ?></div>
  <?php endif; ?>
</div>
<script src="/public/assets/js/data-table.js" defer></script>
<script src="/public/assets/js/tables.js" defer></script>
<?php
    return ob_get_clean();
}

function renderWorkflowTimeline(array $steps, string $currentStep = ''): string
{
    ob_start();
    ?>
<div class="workflow-timeline">
  <?php
  $foundCurrent = false;
  foreach ($steps as $index => $step):
      $label = $step['label'] ?? '';
      $active = $label === $currentStep;
      if ($active) {
          $foundCurrent = true;
      }
      if ($active) {
          $class = 'wf-step active';
      } elseif (!$foundCurrent) {
          $class = 'wf-step done';
      } else {
          $class = 'wf-step';
      }
  ?>
  <div class="<?= htmlspecialchars($class) ?>">
    <div class="wf-step-marker">
      <?php if ($active): ?>
      <i class="fas fa-chevron-right"></i>
      <?php elseif (!$foundCurrent): ?>
      <i class="fas fa-check"></i>
      <?php else: ?>
      <div class="wf-step-num"><?= (int) ($index + 1) ?></div>
      <?php endif; ?>
    </div>
    <div class="wf-step-content">
      <div class="wf-step-label"><?= htmlspecialchars($label) ?></div>
      <?php if (!empty($step['description'])): ?><div class="wf-step-desc"><?= htmlspecialchars($step['description']) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
    return ob_get_clean();
}

function kpiAccentClass(string $accent): string
{
    $map = [
        'red' => 'kpi-icon-bg-pink',
        'blue' => 'kpi-icon-bg-blue',
        'green' => 'kpi-icon-bg-green',
        'amber' => 'kpi-icon-bg-amber',
        'purple' => 'kpi-icon-bg-purple',
        'cyan' => 'kpi-icon-bg-cyan',
    ];

    return $map[$accent] ?? 'kpi-icon-bg-blue';
}
