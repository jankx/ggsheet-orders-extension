<?php
namespace Jankx\Extensions\GGSheetOrders\Admin;

/**
 * Admin settings page for the GGSheet Orders extension.
 *
 * Registered under Settings → "GGSheet Orders" in wp-admin.
 * Stores all configuration in WordPress options:
 *
 *   - jankx_ggsheet_service_account_json  Raw JSON of the SA key file
 *   - jankx_ggsheet_spreadsheet_id        Google Sheets file ID
 *   - jankx_ggsheet_sheet_name            Sheet / tab name (default: Orders)
 *
 * @package Jankx\Extensions\GGSheetOrders\Admin
 */
class GGSheetSettingsPage
{
    const MENU_SLUG = 'jankx-ggsheet-orders-settings';
    const OPTION_GROUP = 'jankx_ggsheet_orders_options';

    const OPT_SERVICE_ACCOUNT = 'jankx_ggsheet_service_account_json';
    const OPT_SPREADSHEET_ID = 'jankx_ggsheet_spreadsheet_id';
    const OPT_SHEET_NAME = 'jankx_ggsheet_sheet_name';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            __('GGSheet Orders Settings', 'jankx'),
            __('GGSheet Orders', 'jankx'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, self::OPT_SERVICE_ACCOUNT, [
            'sanitize_callback' => [$this, 'sanitizeServiceAccountJson'],
        ]);
        register_setting(self::OPTION_GROUP, self::OPT_SPREADSHEET_ID, [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting(self::OPTION_GROUP, self::OPT_SHEET_NAME, [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // ── Section: API Credentials ──────────────────────────────────────
        add_settings_section(
            'jankx_ggsheet_credentials',
            __('Google API Credentials', 'jankx'),
            [$this, 'renderSectionCredentials'],
            self::MENU_SLUG
        );

        add_settings_field(
            self::OPT_SERVICE_ACCOUNT,
            __('Service Account JSON', 'jankx'),
            [$this, 'renderFieldServiceAccount'],
            self::MENU_SLUG,
            'jankx_ggsheet_credentials'
        );

        // ── Section: Spreadsheet Target ───────────────────────────────────
        add_settings_section(
            'jankx_ggsheet_target',
            __('Spreadsheet Target', 'jankx'),
            [$this, 'renderSectionTarget'],
            self::MENU_SLUG
        );

        add_settings_field(
            self::OPT_SPREADSHEET_ID,
            __('Spreadsheet ID', 'jankx'),
            [$this, 'renderFieldSpreadsheetId'],
            self::MENU_SLUG,
            'jankx_ggsheet_target'
        );

        add_settings_field(
            self::OPT_SHEET_NAME,
            __('Sheet / Tab Name', 'jankx'),
            [$this, 'renderFieldSheetName'],
            self::MENU_SLUG,
            'jankx_ggsheet_target'
        );
    }

    // ── Render callbacks ─────────────────────────────────────────────────────

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e('GGSheet Orders Settings', 'jankx'); ?>
            </h1>
            <p>
                <?php esc_html_e(
                    'Configure a Google Service Account to automatically sync orders to a Google Sheet file.',
                    'jankx'
                ); ?>
            </p>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::MENU_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function renderSectionCredentials(): void
    {
        echo '<p>' . esc_html__(
            'Paste the full contents of the Service Account JSON key file downloaded from Google Cloud Console.',
            'jankx'
        ) . '</p>';
    }

    public function renderSectionTarget(): void
    {
        echo '<p>' . esc_html__(
            'Specify which Google Sheet file and which tab/sheet within that file should receive order data.',
            'jankx'
        ) . '</p>';
    }

    public function renderFieldServiceAccount(): void
    {
        $value = get_option(self::OPT_SERVICE_ACCOUNT, '');
        // Show a placeholder instead of the raw JSON so the key is not exposed.
        $placeholder = $value ? __('(saved — paste new JSON to replace)', 'jankx') : '';
        ?>
        <textarea name="<?php echo esc_attr(self::OPT_SERVICE_ACCOUNT); ?>"
            id="<?php echo esc_attr(self::OPT_SERVICE_ACCOUNT); ?>" rows="8" cols="60"
            placeholder="<?php echo esc_attr($placeholder ?: '{ "type": "service_account", ... }'); ?>"
                    class="large-text code"
                ></textarea>
        <p class="description">
            <?php esc_html_e(
                'Leave blank to keep the existing key. The JSON is stored encrypted in the database.',
                'jankx'
            ); ?>
        </p>
        <?php
    }

    public function renderFieldSpreadsheetId(): void
    {
        $value = get_option(self::OPT_SPREADSHEET_ID, '');
        ?>
        <input type="text" name="<?php echo esc_attr(self::OPT_SPREADSHEET_ID); ?>"
            id="<?php echo esc_attr(self::OPT_SPREADSHEET_ID); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text"
            placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms" />
        <p class="description">
            <?php esc_html_e(
                'The long string in the Google Sheets URL between /d/ and /edit.',
                'jankx'
            ); ?>
        </p>
        <?php
    }

    public function renderFieldSheetName(): void
    {
        $value = get_option(self::OPT_SHEET_NAME, 'Orders');
        ?>
        <input type="text" name="<?php echo esc_attr(self::OPT_SHEET_NAME); ?>"
            id="<?php echo esc_attr(self::OPT_SHEET_NAME); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text"
            placeholder="Orders" />
        <p class="description">
            <?php esc_html_e(
                'Name of the sheet tab inside the spreadsheet. Defaults to "Orders".',
                'jankx'
            ); ?>
        </p>
        <?php
    }

    // ── Sanitization ─────────────────────────────────────────────────────────

    /**
     * Validate and sanitize the service account JSON field.
     * Returns the existing saved value when the field is empty (keep old key).
     *
     * @param string $value Raw textarea input.
     *
     * @return string Sanitized JSON string or empty string on parse error.
     */
    public function sanitizeServiceAccountJson(string $value): string
    {
        $value = trim($value);

        // Empty = keep current value.
        if ($value === '') {
            return (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error(
                self::OPT_SERVICE_ACCOUNT,
                'invalid_json',
                __('Service Account JSON is not valid JSON. Please paste the exact file contents.', 'jankx')
            );
            return (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        }

        if (empty($decoded['private_key']) || empty($decoded['client_email'])) {
            add_settings_error(
                self::OPT_SERVICE_ACCOUNT,
                'invalid_sa',
                __('The JSON does not appear to be a valid Service Account key (missing private_key or client_email).', 'jankx')
            );
            return (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        }

        // Re-encode to normalise formatting.
        return (string) json_encode($decoded);
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    public static function getSpreadsheetId(): string
    {
        return (string) get_option(self::OPT_SPREADSHEET_ID, '');
    }

    public static function getSheetName(): string
    {
        return (string) get_option(self::OPT_SHEET_NAME, 'Orders');
    }

    /**
     * Return the decoded service account array, or null if not configured.
     *
     * @return array|null
     */
    public static function getServiceAccount(): ?array
    {
        $json = (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
