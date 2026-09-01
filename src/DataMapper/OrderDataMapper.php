<?php
namespace Jankx\Extensions\GGSheetOrders\DataMapper;

use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Data Mapper: transforms an Order domain object into a flat array of cell
 * values suitable for writing to a Google Sheet row.
 *
 * Column layout (left → right):
 *  A  – Order Number    B  – Date Created    C  – Status
 *  D  – Customer Name   E  – Customer Email  F  – Customer Phone
 *  G  – Customer Address H – Payment Method  I  – Total
 *  J  – Currency        K  – Items (JSON)    L  – Last Updated
 *
 * The column layout can be customized via the filter:
 *   `jankx/ggsheet_orders/row_data`
 *
 * @package Jankx\Extensions\GGSheetOrders\DataMapper
 */
class OrderDataMapper
{
    /**
     * Convert an Order to a flat row array.
     *
     * @param Order $order The order to map.
     *
     * @return array Flat list of cell values.
     */
    public function toRowData(Order $order): array
    {
        $itemsSummary = $this->buildItemsSummary($order);

        $row = [
            $order->getOrderNumber(),
            $order->getDateCreated(),
            $order->getStatus(),
            $order->getCustomerName(),
            $order->getCustomerEmail(),
            $order->getCustomerPhone(),
            $order->getCustomerAddress(),
            $order->getPaymentMethod(),
            $order->getTotal(),
            $order->getCurrency(),
            $itemsSummary,
            current_time('mysql'),
        ];

        /**
         * Filter the row data before it is written to the Google Sheet.
         *
         * @param array $row   Flat cell values.
         * @param Order $order The source order.
         */
        return (array) apply_filters('jankx/ggsheet_orders/row_data', $row, $order);
    }

    /**
     * Return the header row that should be written to the first row of a
     * freshly created sheet so column labels are self-documenting.
     *
     * @return array
     */
    public function getHeaderRow(): array
    {
        $headers = [
            'Order Number',
            'Date Created',
            'Status',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Customer Address',
            'Payment Method',
            'Total',
            'Currency',
            'Items',
            'Last Updated',
        ];

        return (array) apply_filters('jankx/ggsheet_orders/header_row', $headers);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a compact human-readable summary of order items.
     * e.g. "Tour Hạ Long (2) – Tour Sapa (1)"
     */
    private function buildItemsSummary(Order $order): string
    {
        $parts = [];

        foreach ($order->getItems() as $item) {
            $parts[] = sprintf('%s (%d)', $item->getName(), $item->getQuantity());
        }

        return implode(' – ', $parts);
    }
}
