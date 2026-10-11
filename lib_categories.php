<?php
// ---------------------------------------------------------------------------
// Category helpers.
//
// Every page reads categories and categorisation rules from the database
// through these functions, so the list lives in one place (the categories
// and category_rules tables) instead of being copied into each page.
// ---------------------------------------------------------------------------

/**
 * All categories in display order, as rows with id, name, colour.
 * Cached for the rest of the request, since several parts of a page ask.
 */
function get_categories(PDO $pdo)
{
    static $cache = null;
    if ($cache === null) {
        $stmt = $pdo->query("SELECT id, name, colour FROM categories ORDER BY sort_order, name");
        $cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $cache;
}

/** Category names only, in display order (for dropdowns). */
function get_category_names(PDO $pdo)
{
    $names = array();
    foreach (get_categories($pdo) as $c) {
        $names[] = $c['name'];
    }
    return $names;
}

/** Map of category name => hex colour (for charts and badges). */
function get_category_colours(PDO $pdo)
{
    $colours = array();
    foreach (get_categories($pdo) as $c) {
        $colours[$c['name']] = $c['colour'];
    }
    return $colours;
}

/**
 * Rule-based categorisation.
 *
 * Returns array('category' => name, 'keyword' => matched keyword or null,
 * 'source' => 'yours' | 'built-in' | 'default').
 *
 * Order of checking:
 *   1. the user's own rules, so a user can override a built-in default
 *   2. built-in rules
 *   within each group, longer keywords first, so a specific keyword such
 *   as "pizza hut" beats a general one such as "pizza".
 * If nothing matches, the transaction falls back to "Others".
 */
function categorise_merchant(PDO $pdo, $user_id, $merchant)
{
    $fallback = array('category' => 'Others', 'keyword' => null, 'source' => 'default');

    $m = strtolower(trim((string) $merchant));
    if ($m === '') {
        return $fallback;
    }

    $stmt = $pdo->prepare(
        "SELECT r.keyword, r.user_id, c.name
         FROM category_rules r
         JOIN categories c ON c.id = r.category_id
         WHERE r.user_id = ? OR r.user_id IS NULL
         ORDER BY (r.user_id IS NULL) ASC, CHAR_LENGTH(r.keyword) DESC, r.keyword"
    );
    $stmt->execute(array($user_id));

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (strpos($m, $row['keyword']) !== false) {
            return array(
                'category' => $row['name'],
                'keyword'  => $row['keyword'],
                'source'   => $row['user_id'] === null ? 'built-in' : 'yours',
            );
        }
    }
    return $fallback;
}

/** Just the category name, for callers that don't need the explanation. */
function categorise($pdo, $user_id, $merchant)
{
    $result = categorise_merchant($pdo, $user_id, $merchant);
    return $result['category'];
}