<?php
namespace Jankx\Extensions\GGSheetOrders\DataMapper;

use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\GGSheetOrders\Registry\ColumnRegistry;

/**
 * Data Mapper: transforms an Order domain object into a flat array of cell
 * values suitable for writing to a Google Sheet row.
 *
 * It dynamically relies on the ColumnRegistry to extract values.
 *
 * @package Jankx\Extensions\GGSheetOrders\DataMapper
 */
class OrderDataMapper
{
    private ColumnRegistry $registry;

    public function __construct(?ColumnRegistry $registry = null)
    {
        $this->registry = $registry ?? new ColumnRegistry();
    }

    /**
     * Convert an Order to a flat row array (Left → Right).
     *
     * @param Order $order The order to map.
     * @return array Flat list of cell values.
     */
    public function toRowData(Order $order): array
    {
        $row = [];
        foreach ($this->registry->getColumns() as $column) {
            $row[] = $column->getValue($order);
        }

        // Deprecated backwards compatibility: 
        // Previously we used `jankx/ggsheet_orders/row_data`. 
        // We still trigger it, but the recommended way is using `register_columns`.
        return (array) apply_filters('jankx/ggsheet_orders/row_data', $row, $order);
    }

    /**
     * Return the header row mapped directly from Registry labels.
     *
     * @return array
     */
    public function getHeaderRow(): array
    {
        $headers = [];
        foreach ($this->registry->getColumns() as $column) {
            $headers[] = $column->getLabel();
        }

        return (array) apply_filters('jankx/ggsheet_orders/header_row', $headers);
    }

    public function getRegistry(): ColumnRegistry
    {
        return $this->registry;
    }
}
