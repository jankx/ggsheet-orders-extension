<?php
namespace Jankx\Extensions\GGSheetOrders\Registry;

use Jankx\Extensions\Ecommerce\Order\Order;

/**
 * Data Transfer Object representing a single column in the Google Sheet.
 *
 * @package Jankx\Extensions\GGSheetOrders\Registry
 */
class Column
{
    private string $id;
    private string $label;
    private int $priority;
    /** @var callable */
    private $valueCallback;

    public function __construct(string $id, string $label, int $priority, callable $valueCallback)
    {
        $this->id = $id;
        $this->label = $label;
        $this->priority = $priority;
        $this->valueCallback = $valueCallback;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Resolve the cell value for this column given an Order.
     */
    public function getValue(Order $order)
    {
        return call_user_func($this->valueCallback, $order);
    }
}
