<?php
namespace Jankx\Extensions\GGSheetOrders\Repository;

/**
 * Repository interface for persisting and retrieving the Google Sheet row
 * index that corresponds to a given order.
 *
 * Decoupling storage from business logic allows the implementation to be
 * swapped (e.g. WordPress options → postmeta → custom table) without touching
 * any other part of the extension.
 *
 * @package Jankx\Extensions\GGSheetOrders\Repository
 */
interface RowIndexRepositoryInterface
{
    /**
     * Retrieve the 1-based sheet row index previously stored for an order.
     *
     * @param int $orderId Internal order ID.
     *
     * @return int|null The stored row index, or null if none exists yet.
     */
    public function getRowIndex(int $orderId): ?int;

    /**
     * Persist (create or overwrite) the sheet row index for an order.
     *
     * @param int $orderId  Internal order ID.
     * @param int $rowIndex 1-based sheet row index.
     *
     * @return bool True when the value was saved successfully.
     */
    public function saveRowIndex(int $orderId, int $rowIndex): bool;

    /**
     * Remove the stored row index for an order (e.g. on order deletion).
     *
     * @param int $orderId Internal order ID.
     *
     * @return bool True when the entry was removed (or did not exist).
     */
    public function deleteRowIndex(int $orderId): bool;
}
