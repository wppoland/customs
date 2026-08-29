<?php
/**
 * Self-check for tariff-line grouping. Run: php tests/tariff-line-key-check.php
 *
 * Covers the case a merchant reported: books split across Books > Health and
 * Books > Self improvement were counted as two tariff lines and charged twice,
 * and a shared tariff code was expected to collapse them into one.
 *
 * No framework on purpose. WordPress and WooCommerce are stubbed down to the
 * three functions and two shapes this class actually touches.
 *
 * @package plogins-customs
 */

define('ABSPATH', __DIR__);
define('CUSTOMS_DIR', __DIR__ . '/../');

// term id => parent id. 10 Books, 11 Health, 12 Self improvement, 20 Gifts.
const TERM_PARENTS = [10 => 0, 11 => 10, 12 => 10, 20 => 0, 21 => 20];

function get_ancestors(int $id, string $taxonomy, string $type = ''): array
{
    $out = [];
    while ((TERM_PARENTS[$id] ?? 0) > 0) {
        $id    = TERM_PARENTS[$id];
        $out[] = $id;
    }

    return $out;
}

function apply_filters(string $hook, $value, ...$args)
{
    return $value;
}

function wc_get_product($id)
{
    return null;
}

function get_option(string $name, $default = false)
{
    // SettingsRepository is final, so the basis is fed through the option it
    // actually reads rather than through a subclass.
    return ['count_basis' => 'category'];
}

abstract class WC_Product_Stub
{
    /** @param list<int> $categories */
    public function __construct(
        private int $id,
        private array $categories = [],
        private string $code = '',
        private int $parent = 0,
    ) {
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_parent_id(): int
    {
        return $this->parent;
    }

    /** @return list<int> */
    public function get_category_ids(): array
    {
        return $this->categories;
    }

    public function get_meta(string $key, bool $single = false): string
    {
        return $this->code;
    }
}

class_alias('WC_Product_Stub', 'WC_Product_Base');
eval('class WC_Product extends WC_Product_Base {}');

class WC_Cart
{
    /** @param list<WC_Product> $products */
    public function __construct(private array $products)
    {
    }

    public function get_cart(): array
    {
        return array_map(static fn ($p) => ['data' => $p], $this->products);
    }
}

require __DIR__ . '/../src/Settings/SettingsRepository.php';
require __DIR__ . '/../src/Duty/TariffLineCounter.php';

use Customs\Duty\TariffLineCounter;
use Customs\Settings\SettingsRepository;

$counter = new TariffLineCounter(new SettingsRepository());

$assert = static function (string $label, int $got, int $want): void {
    if ($got !== $want) {
        fwrite(STDERR, sprintf("FAIL %s: expected %d, got %d\n", $label, $want, $got));
        exit(1);
    }
    printf("ok   %s (%d)\n", $label, $got);
};

// The reported case: two books in two subcategories of Books.
$assert(
    'two subcategories of one parent are one line',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11]),
        new WC_Product(2, [12]),
    ])),
    1,
);

// Different top-level categories still count separately.
$assert(
    'different top-level categories are separate lines',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11]),
        new WC_Product(3, [21]),
    ])),
    2,
);

// The answer must not depend on the merchant also ticking the parent term.
$assert(
    'ticking the parent as well changes nothing',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11]),
        new WC_Product(2, [10, 12]),
    ])),
    1,
);

// A shared tariff code overrides categories entirely.
$assert(
    'a shared tariff code collapses unrelated categories',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11], '4901'),
        new WC_Product(3, [21], '4901'),
    ])),
    1,
);

echo "all tariff-line grouping checks passed\n";
