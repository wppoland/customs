<?php

declare(strict_types=1);

namespace Customs\Duty;

use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Counts the distinct "tariff lines" in a cart.
 *
 * The EU rule charges the flat duty per distinct tariff classification in a
 * consignment, not per unit: three identical shirts are one line, while a shirt
 * plus a lipstick are two. Real tariff classification (HS codes) is out of
 * scope for the FREE MVP, so a tariff line is approximated by one of:
 *
 *   1. an explicit tariff code set on the product or variation
 *      (meta key _customs_tariff_code), when present;
 *   2. otherwise, the TOP-LEVEL category the product sits under (default
 *      basis), so Books > Health and Books > Self improvement are one line
 *      rather than two; or
 *   3. otherwise, the product itself (product basis, or category fallback when
 *      a product has no category).
 *
 * Mapping precise HS codes and per-line lookups is left to Customs Pro.
 */
final class TariffLineCounter
{
    public const META_KEY = '_customs_tariff_code';

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * Number of distinct tariff lines in the given cart.
     *
     * @param \WC_Cart $cart
     */
    public function count(\WC_Cart $cart): int
    {
        $basis = $this->settings->countBasis();
        $keys  = [];

        foreach ($cart->get_cart() as $item) {
            if (! is_array($item)) {
                continue;
            }

            $product = $item['data'] ?? null;
            if (! $product instanceof \WC_Product) {
                continue;
            }

            $keys[$this->lineKey($product, $basis)] = true;
        }

        $count = count($keys);

        /**
         * Filters the number of distinct tariff lines counted for a cart.
         *
         * @param int      $count Distinct tariff lines.
         * @param \WC_Cart $cart  The cart being evaluated.
         * @param string   $basis The active count basis (category|product).
         */
        $count = (int) apply_filters('customs/tariff_line_count', $count, $cart, $basis);

        return max(0, $count);
    }

    /**
     * Resolve a stable grouping key for a single product line.
     */
    private function lineKey(\WC_Product $product, string $basis): string
    {
        $key = $this->resolveKey($product, $basis);

        /**
         * Filters the grouping key for a single cart line.
         *
         * Two products that resolve to the same key count as one tariff line.
         * Use this to classify by something the plugin does not know about, such
         * as a real HS heading held in another plugin's meta.
         *
         * @param string      $key     The resolved grouping key.
         * @param \WC_Product $product The product being keyed.
         * @param string      $basis   The active count basis (category|product).
         */
        return (string) apply_filters('customs/tariff_line_key', $key, $product, $basis);
    }

    private function resolveKey(\WC_Product $product, string $basis): string
    {
        $code = $this->explicitCode($product);
        if ('' !== $code) {
            return 'code:' . $code;
        }

        if (SettingsRepository::BASIS_CATEGORY === $basis) {
            $category = $this->groupCategoryId($product);
            if ($category > 0) {
                return 'cat:' . $category;
            }
        }

        // Product basis, or category basis with no category assigned. Variations
        // group under their parent so size/colour variants count as one line.
        $parent = $product->get_parent_id();

        return 'prod:' . ($parent > 0 ? $parent : $product->get_id());
    }

    /**
     * Explicit tariff code stored on the variation or its parent product.
     */
    private function explicitCode(\WC_Product $product): string
    {
        $code = trim((string) $product->get_meta(self::META_KEY, true));
        if ('' !== $code) {
            return $code;
        }

        $parent = $product->get_parent_id();
        if ($parent > 0) {
            $parentProduct = wc_get_product($parent);
            if ($parentProduct instanceof \WC_Product) {
                $code = trim((string) $parentProduct->get_meta(self::META_KEY, true));
            }
        }

        return $code;
    }

    /**
     * Top-level category the product groups under.
     *
     * Every assigned category is walked up to its own top-level ancestor, and
     * the lowest of those ids wins. Two things follow, both of them the point:
     * a shop whose books live in Books > Health and Books > Self improvement
     * gets one line rather than two, and the answer does not depend on which
     * term WooCommerce happens to return first or on whether the merchant also
     * ticked the parent category.
     */
    private function groupCategoryId(\WC_Product $product): int
    {
        $ids = $product->get_category_ids();
        if (empty($ids) && $product->get_parent_id() > 0) {
            $parentProduct = wc_get_product($product->get_parent_id());
            if ($parentProduct instanceof \WC_Product) {
                $ids = $parentProduct->get_category_ids();
            }
        }

        $tops = [];

        foreach ($ids as $id) {
            $top = $this->topAncestorId((int) $id);
            if ($top > 0) {
                $tops[] = $top;
            }
        }

        if ([] === $tops) {
            return 0;
        }

        sort($tops, SORT_NUMERIC);

        return (int) $tops[0];
    }

    /**
     * The outermost ancestor of a product category, or the term itself when it
     * is already top level.
     */
    private function topAncestorId(int $termId): int
    {
        if ($termId <= 0) {
            return 0;
        }

        $ancestors = get_ancestors($termId, 'product_cat', 'taxonomy');

        // get_ancestors() returns nearest first, so the outermost is last.
        if (is_array($ancestors) && [] !== $ancestors) {
            return (int) end($ancestors);
        }

        return $termId;
    }
}
