<?php
namespace Jankx\Extensions\GGSheetOrders\Client;

/**
 * Concrete Google Sheets API v4 client using a Service Account JSON key.
 *
 * Authentication is handled by signing a JWT with the service account private
 * key and exchanging it for a short-lived Bearer access token (Google OAuth2
 * token endpoint).  No external library is required — only PHP's openssl and
 * wp_remote_* functions.
 *
 * Settings are read from WordPress options (managed by GGSheetSettingsPage):
 *   - jankx_ggsheet_service_account_json  : raw JSON string of the SA key file
 *   - jankx_ggsheet_spreadsheet_id        : default spreadsheet ID
 *   - jankx_ggsheet_sheet_name            : default sheet/tab name
 *
 * @package Jankx\Extensions\GGSheetOrders\Client
 */
class ServiceAccountSheetClient implements GoogleSheetClientInterface
{
    /** Google OAuth2 token endpoint. */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Sheets API base URL. */
    const SHEETS_API_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';

    /** Required scope for reading & writing sheet data. */
    const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    /** WordPress transient key that caches the access token. */
    const TOKEN_TRANSIENT = 'jankx_ggsheet_access_token';

    /** @var array Decoded service-account key JSON. */
    private array $serviceAccount;

    /**
     * @param array $serviceAccount Decoded JSON array of the SA key file.
     *                              Keys: type, project_id, private_key_id,
     *                              private_key, client_email, token_uri …
     * @throws \InvalidArgumentException When the array does not look like a valid SA key.
     */
    public function __construct(array $serviceAccount)
    {
        if (empty($serviceAccount['private_key']) || empty($serviceAccount['client_email'])) {
            throw new \InvalidArgumentException(
                'Service account JSON must contain "private_key" and "client_email".'
            );
        }

        $this->serviceAccount = $serviceAccount;
    }

    // -------------------------------------------------------------------------
    // GoogleSheetClientInterface
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * Uses the Sheets API `values.append` endpoint with INPUT_VALUE_OPTION=RAW
     * and INSERT_DATA_OPTION=INSERT_ROWS so that each call adds a brand-new row
     * rather than overwriting existing data.
     */
    public function appendRow(string $spreadsheetId, string $sheetName, array $rowData): ?int
    {
        $token = $this->getAccessToken();
        if (!$token) {
            $this->logError('appendRow: could not obtain access token.');
            return null;
        }

        $range = $this->buildRange($sheetName);
        $url = sprintf(
            '%s/%s/values/%s:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
            self::SHEETS_API_BASE,
            rawurlencode($spreadsheetId),
            rawurlencode($range)
        );

        $body = json_encode([
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => [$rowData],
        ]);

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'body' => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->logError('appendRow wp_remote_post error: ' . $response->get_error_message());
            return null;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            $this->logError(sprintf(
                'appendRow HTTP %d: %s',
                $statusCode,
                wp_remote_retrieve_body($response)
            ));
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        // updatedRange is e.g. "Sheet1!A5:E5" — extract the row number.
        return $this->parseRowIndexFromRange($data['updates']['updatedRange'] ?? '');
    }

    /**
     * {@inheritdoc}
     *
     * Uses the Sheets API `values.update` endpoint to overwrite a specific row.
     */
    public function updateRow(string $spreadsheetId, string $sheetName, int $rowIndex, array $rowData): bool
    {
        $token = $this->getAccessToken();
        if (!$token) {
            $this->logError('updateRow: could not obtain access token.');
            return false;
        }

        // Build an A1-notation range for the entire row, e.g. "Sheet1!A5:ZZ5"
        $range = sprintf('%s!A%d:ZZ%d', $sheetName, $rowIndex, $rowIndex);
        $url = sprintf(
            '%s/%s/values/%s?valueInputOption=RAW',
            self::SHEETS_API_BASE,
            rawurlencode($spreadsheetId),
            rawurlencode($range)
        );

        $body = json_encode([
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => [$rowData],
        ]);

        $response = wp_remote_request($url, [
            'method' => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json; charset=UTF-8',
            ],
            'body' => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->logError('updateRow wp_remote_request error: ' . $response->get_error_message());
            return false;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            $this->logError(sprintf(
                'updateRow HTTP %d: %s',
                $statusCode,
                wp_remote_retrieve_body($response)
            ));
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // JWT / OAuth2 helpers
    // -------------------------------------------------------------------------

    /**
     * Return a valid access token, fetching a new one from Google when the
     * cached transient has expired or does not exist.
     */
    private function getAccessToken(): ?string
    {
        $cached = get_transient(self::TOKEN_TRANSIENT);
        if ($cached) {
            return $cached;
        }

        $jwt = $this->buildJwt();
        if (!$jwt) {
            return null;
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->logError('getAccessToken error: ' . $response->get_error_message());
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            $this->logError('getAccessToken: no access_token in response. ' . wp_remote_retrieve_body($response));
            return null;
        }

        // Cache for slightly less than the token TTL (default 3600 s).
        $ttl = max(60, (int) ($data['expires_in'] ?? 3600) - 60);
        set_transient(self::TOKEN_TRANSIENT, $data['access_token'], $ttl);

        return $data['access_token'];
    }

    /**
     * Build a signed JWT for the service account.
     *
     * @return string|null Base64url-encoded signed JWT, or null on error.
     */
    private function buildJwt(): ?string
    {
        $now = time();

        $header = $this->base64urlEncode((string) json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));

        $payload = $this->base64urlEncode((string) json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = $header . '.' . $payload;

        $privateKey = openssl_pkey_get_private($this->serviceAccount['private_key']);
        if (!$privateKey) {
            $this->logError('buildJwt: failed to load private key.');
            return null;
        }

        $signature = '';
        if (!openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $this->logError('buildJwt: openssl_sign failed.');
            return null;
        }

        return $signingInput . '.' . $this->base64urlEncode($signature);
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /**
     * Build a range string like "Sheet1" which the Sheets API uses to select
     * the entire first sheet when appending.
     */
    private function buildRange(string $sheetName): string
    {
        return $sheetName;
    }

    /**
     * Extract the 1-based row number from an A1-notation range.
     * e.g. "Sheet1!A5:E5" → 5
     */
    private function parseRowIndexFromRange(string $range): ?int
    {
        // Match digits that immediately follow a letter+optional-colon boundary.
        if (preg_match('/[A-Z]+(\d+)/', $range, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function logError(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[GGSheetOrders] ' . $message);
        }
    }
}
