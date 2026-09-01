<?php
namespace Jankx\Extensions\GGSheetOrders\Repository;

/**
 * WordPress-backed implementation of RowIndexRepositoryInterface.
 *
 * Uses the wp_options table with a namespaced key per order ID so that no
 * custom database table migration is required for this lightweight mapping.
 *
 * Key format: jankx_ggsheet_row_{$orderId}
 *
 * @package Jankx\Extensions\GGSheetOrders\Repository
 */
class WordPressRowIndexRepository implements RowIndexRepositoryInterface
{
    /** Option key prefix. */
    const KEY_PREFIX = 'jankx_ggsheet_row_';

    /** {@inheritdoc} */
    public function getRowIndex(int $orderId): ?int
    {
        $value = get_option($this->buildKey($orderId), null);

        if ($value === null || $value === false) {
            return null;
        }

        return (int) $value;
    }

    /** {@inheritdoc} */
    public function saveRowIndex(int $orderId, int $rowIndex): bool
    {
        $key = $this->buildKey($orderId);

        // update_option returns false when the value has not changed, which is
        // still a "success" scenario — use add_option on first write.
        if (get_option($key, null) === null) {
            return (bool) add_option($key, $rowIndex, '', 'no');
        }

        return (bool) update_option($key, $rowIndex, 'no');
    }

    /** {@inheritdoc} */
    public function deleteRowIndex(int $orderId): bool
    {
        return delete_option($this->buildKey($orderId));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildKey(int $orderId): string
    {
        return self::KEY_PREFIX . $orderId;
    }
}
