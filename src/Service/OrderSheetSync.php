<?php
namespace Jankx\Extensions\GGSheetOrders\Service;

use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\GGSheetOrders\Client\GoogleSheetClientInterface;
use Jankx\Extensions\GGSheetOrders\DataMapper\OrderDataMapper;
use Jankx\Extensions\GGSheetOrders\Repository\RowIndexRepositoryInterface;

/**
 * Service Layer: orchestrates writing and updating order data in a Google Sheet.
 *
 * This class owns the high-level use-cases:
 *   1. `syncNewOrder`     – called when an order is first created.
 *   2. `syncOrderUpdate`  – called when an order is edited or its status changes.
 *
 * It delegates transport to {@see GoogleSheetClientInterface}, persistence of
 * the row-index mapping to {@see RowIndexRepositoryInterface}, and data
 * transformation to {@see OrderDataMapper}.
 *
 * @package Jankx\Extensions\GGSheetOrders\Service
 */
class OrderSheetSync
{
    /** @var GoogleSheetClientInterface */
    private GoogleSheetClientInterface $client;

    /** @var RowIndexRepositoryInterface */
    private RowIndexRepositoryInterface $rowIndexRepo;

    /** @var OrderDataMapper */
    private OrderDataMapper $mapper;

    /** @var string Google Sheets file ID. */
    private string $spreadsheetId;

    /** @var string Sheet / tab name. */
    private string $sheetName;

    public function __construct(
        GoogleSheetClientInterface $client,
        RowIndexRepositoryInterface $rowIndexRepo,
        OrderDataMapper $mapper,
        string $spreadsheetId,
        string $sheetName
    ) {
        $this->client = $client;
        $this->rowIndexRepo = $rowIndexRepo;
        $this->mapper = $mapper;
        $this->spreadsheetId = $spreadsheetId;
        $this->sheetName = $sheetName;
    }

    // -------------------------------------------------------------------------
    // Public use-cases
    // -------------------------------------------------------------------------

    /**
     * Append a brand-new row for a newly created order and store the row index.
     *
     * @param Order $order The newly created order.
     * @param Cart  $cart  The cart that generated the order (unused directly,
     *                     available for filters/extensions).
     *
     * @return bool True when the row was appended and the index was stored.
     */
    public function syncNewOrder(Order $order, Cart $cart): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $rowData = $this->mapper->toRowData($order);
        $rowIndex = $this->client->appendRow($this->spreadsheetId, $this->sheetName, $rowData);

        if ($rowIndex === null) {
            $this->logWarning(sprintf(
                'syncNewOrder: appendRow returned null for order #%s.',
                $order->getOrderNumber()
            ));
            return false;
        }

        $saved = $this->rowIndexRepo->saveRowIndex($order->getId(), $rowIndex);

        do_action('jankx/ggsheet_orders/after_sync_new_order', $order, $rowIndex, $saved);

        return $saved;
    }

    /**
     * Update the existing row for an order whose data or status has changed.
     *
     * If no row index has been stored yet (e.g. the initial append failed),
     * the method falls back to appending a new row so data is never lost.
     *
     * @param Order $order The updated order.
     *
     * @return bool True when the sheet was updated successfully.
     */
    public function syncOrderUpdate(Order $order): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $rowIndex = $this->rowIndexRepo->getRowIndex($order->getId());
        $rowData = $this->mapper->toRowData($order);

        if ($rowIndex !== null) {
            $result = $this->client->updateRow($this->spreadsheetId, $this->sheetName, $rowIndex, $rowData);

            do_action('jankx/ggsheet_orders/after_sync_order_update', $order, $rowIndex, $result);

            return $result;
        }

        // Fallback: append as a new row (should not happen under normal circumstances).
        $this->logWarning(sprintf(
            'syncOrderUpdate: no stored row index for order #%s — falling back to append.',
            $order->getOrderNumber()
        ));

        $newRowIndex = $this->client->appendRow($this->spreadsheetId, $this->sheetName, $rowData);
        if ($newRowIndex === null) {
            return false;
        }

        $saved = $this->rowIndexRepo->saveRowIndex($order->getId(), $newRowIndex);

        do_action('jankx/ggsheet_orders/after_sync_order_update', $order, $newRowIndex, $saved);

        return $saved;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Guard: returns false when required settings are missing so the service
     * silently skips instead of throwing errors.
     */
    private function isConfigured(): bool
    {
        return $this->spreadsheetId !== '' && $this->sheetName !== '';
    }

    private function logWarning(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GGSheetOrders] ' . $message);
        }
    }
}
