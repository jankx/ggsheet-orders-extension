<?php
namespace Jankx\Extensions\GGSheetOrders\Registry;

use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Manages the Registration and Sorting of Google Sheet Columns.
 *
 * This allows third-party extensions to hook in and inject their own columns
 * dynamically into the Google Sheet without breaking the structure.
 *
 * @package Jankx\Extensions\GGSheetOrders\Registry
 */
class ColumnRegistry
{
    /**
     * @var Column[]
     */
    private array $columns = [];

    public function __construct()
    {
        $this->registerCoreColumns();

        /**
         * Gives other extensions the opportunity to add their custom columns.
         * Default priorities:
         * 10-90: Basic Info
         * 100-190: Customer Info
         * 200-290: Pricing & Payment
         * 300+: Metadata / Extensions
         * 
         * @param ColumnRegistry $registry
         */
        do_action('jankx/ggsheet_orders/register_columns', $this);
    }

    public function addColumn(string $id, string $label, int $priority, callable $valueCallback): self
    {
        $this->columns[$id] = new Column($id, $label, $priority, $valueCallback);
        return $this;
    }

    public function removeColumn(string $id): self
    {
        unset($this->columns[$id]);
        return $this;
    }

    /**
     * Retrieve all columns sorted by priority.
     *
     * @return Column[]
     */
    public function getColumns(): array
    {
        $sorted = $this->columns;
        uasort($sorted, function (Column $a, Column $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        return array_values($sorted);
    }

    /**
     * Build a compact human-readable summary of order items.
     */
    private function buildItemsSummary(Order $order): string
    {
        $parts = [];
        foreach ($order->getItems() as $item) {
            $parts[] = sprintf('%s (%d)', $item->getName(), $item->getQuantity());
        }
        return implode(' – ', $parts);
    }

    private function registerCoreColumns(): void
    {
        // ── Basic Info (10 - 90) ──
        $this->addColumn('order_number', __('Số đơn hàng', 'jankx'), 10, function (Order $order) {
            return $order->getOrderNumber();
        });
        $this->addColumn('date_created', __('Ngày tạo', 'jankx'), 20, function (Order $order) {
            return $order->getDateCreated();
        });
        $this->addColumn('status', __('Trạng thái', 'jankx'), 30, function (Order $order) {
            return $order->getStatus(); // Or optionally Order::getStatusLabel()
        });

        // ── Customer Info (100 - 190) ──
        $this->addColumn('customer_name', __('Tên khách hàng', 'jankx'), 100, function (Order $order) {
            return $order->getCustomerName();
        });
        $this->addColumn('customer_email', __('Email', 'jankx'), 110, function (Order $order) {
            return $order->getCustomerEmail();
        });
        $this->addColumn('customer_phone', __('Điện thoại', 'jankx'), 120, function (Order $order) {
            return $order->getCustomerPhone();
        });
        $this->addColumn('customer_address', __('Địa chỉ', 'jankx'), 130, function (Order $order) {
            return $order->getCustomerAddress();
        });

        // ── Payment & Core Items (200 - 290) ──
        $this->addColumn('payment_method', __('Phương thức thanh toán', 'jankx'), 200, function (Order $order) {
            return $order->getPaymentMethod();
        });
        $this->addColumn('subtotal', __('Tổng tiền đơn hàng', 'jankx'), 210, function (Order $order) {
            $subtotal = 0;
            foreach ($order->getItems() as $item) {
                $subtotal += $item->getTotal(); // Tổng giá trị raw của các mặt hàng
            }
            return $subtotal;
        });
        $this->addColumn('total', __('Tổng thanh toán', 'jankx'), 220, function (Order $order) {
            return $order->getTotal(); // Grand total: đã bao gồm coupon, phí, v.v.
        });
        $this->addColumn('currency', __('Tiền tệ', 'jankx'), 230, function (Order $order) {
            return $order->getCurrency();
        });
        $this->addColumn('items', __('Sản phẩm (Tóm tắt)', 'jankx'), 240, function (Order $order) {
            return $this->buildItemsSummary($order);
        });

        // ── Metadata (900+) ──
        $this->addColumn('last_updated', __('Cập nhật lần cuối', 'jankx'), 900, function (Order $order) {
            return current_time('mysql');
        });
    }
}
