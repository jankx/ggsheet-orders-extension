<?php
namespace Jankx\Extensions\GGSheetOrders\Client;

/**
 * Strategy interface for Google Sheets API communication.
 *
 * Any concrete client implementation (REST v4, service-account JWT, OAuth …)
 * must implement this contract so the rest of the extension is completely
 * decoupled from the transport layer.
 *
 * @package Jankx\Extensions\GGSheetOrders\Client
 */
interface GoogleSheetClientInterface
{
    /**
     * Append a new row to the end of the sheet and return the 1-based row
     * index where the data was written (useful for later in-place updates).
     *
     * @param string  $spreadsheetId Google Sheets file ID.
     * @param string  $sheetName     Sheet / tab name inside the file.
     * @param array   $rowData       Flat list of cell values (left → right).
     *
     * @return int|null The 1-based row index that was appended, null on failure.
     */
    public function appendRow(string $spreadsheetId, string $sheetName, array $rowData): ?int;

    /**
     * Update a specific row (1-based index) in the sheet.
     *
     * @param string  $spreadsheetId Google Sheets file ID.
     * @param string  $sheetName     Sheet / tab name inside the file.
     * @param int     $rowIndex      1-based row index to overwrite.
     * @param array   $rowData       New cell values (left → right).
     *
     * @return bool True when the update succeeded.
     */
    public function updateRow(string $spreadsheetId, string $sheetName, int $rowIndex, array $rowData): bool;
}
