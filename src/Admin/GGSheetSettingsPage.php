<?php
namespace Jankx\Extensions\GGSheetOrders\Admin;

use Jankx\Extensions\Ecommerce\Admin\EcommerceSettingsPage;
use Jankx\Extensions\GGSheetOrders\Registry\ColumnRegistry;

/**
 * Integrates GGSheet Orders settings as a tab inside the Ecommerce Settings page.
 *
 * Instead of registering a standalone admin menu page, this class hooks into
 * the two extension points exposed by EcommerceSettingsPage:
 *
 *   - Filter `jankx/ecommerce/settings/tabs`      → register the "Google Sheet" tab label
 *   - Action `jankx/ecommerce/settings/render_tab` → render the settings form when active
 *
 * Options are saved to the same EcommerceSettingsPage option group so they are
 * processed by WordPress's built-in options.php handler with no extra routing.
 *
 * Settings stored:
 *   - jankx_ggsheet_service_account_json  : raw (normalised) JSON of the SA key
 *   - jankx_ggsheet_spreadsheet_id        : Google Sheets file ID
 *   - jankx_ggsheet_sheet_name            : sheet / tab name (default: Orders)
 *
 * @package Jankx\Extensions\GGSheetOrders\Admin
 */
class GGSheetSettingsPage
{
    /** Tab slug registered in EcommerceSettingsPage. */
    const TAB_SLUG = 'ggsheet';

    const OPT_SERVICE_ACCOUNT = 'jankx_ggsheet_service_account_json';
    const OPT_SPREADSHEET_ID = 'jankx_ggsheet_spreadsheet_id';
    const OPT_SHEET_NAME = 'jankx_ggsheet_sheet_name';

    public function register(): void
    {
        // Inject our tab label into the Ecommerce Settings tab list.
        add_filter('jankx/ecommerce/settings/tabs', [$this, 'registerTab']);

        // Render our settings form when our tab is active.
        add_action('jankx/ecommerce/settings/render_tab', [$this, 'renderTab']);

        // Register our options under the shared Ecommerce option group so that
        // WordPress's options.php handles the save without custom routing.
        add_action('admin_init', [$this, 'registerSettings']);
    }

    // -------------------------------------------------------------------------
    // Hook callbacks
    // -------------------------------------------------------------------------

    /**
     * Append the "Google Sheet" tab to the Ecommerce Settings tab list.
     *
     * @param array $tabs Existing tabs (slug => label).
     * @return array
     */
    public function registerTab(array $tabs): array
    {
        $tabs[self::TAB_SLUG] = __('Google Sheet', 'jankx');
        return $tabs;
    }

    /**
     * Render the settings form when our tab slug is active.
     *
     * @param string $currentTab Active tab slug passed by EcommerceSettingsPage.
     */
    public function renderTab(string $currentTab): void
    {
        if ($currentTab !== self::TAB_SLUG) {
            return;
        }

        $this->render();
    }

    // -------------------------------------------------------------------------
    // Settings registration
    // -------------------------------------------------------------------------

    public function registerSettings(): void
    {
        register_setting(EcommerceSettingsPage::OPTION_GROUP, self::OPT_SERVICE_ACCOUNT, [
            'sanitize_callback' => [$this, 'sanitizeServiceAccountJson'],
        ]);

        register_setting(EcommerceSettingsPage::OPTION_GROUP, self::OPT_SPREADSHEET_ID, [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting(EcommerceSettingsPage::OPTION_GROUP, self::OPT_SHEET_NAME, [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    private function render(): void
    {
        ?>
        <h2><?php esc_html_e('Google Sheet — Đồng bộ đơn hàng', 'jankx'); ?></h2>
        <p class="description">
            <?php esc_html_e(
                'Cấu hình Google Service Account để tự động ghi đơn hàng mới vào Google Sheet và cập nhật dòng tương ứng khi đơn hàng thay đổi.',
                'jankx'
            ); ?>
        </p>

        <form method="post" action="options.php">
            <?php settings_fields(EcommerceSettingsPage::OPTION_GROUP); ?>

            <h3><?php esc_html_e('Thông tin xác thực Google API', 'jankx'); ?></h3>
            <p class="description">
                <?php esc_html_e(
                    'Paste toàn bộ nội dung file JSON key tải từ Google Cloud Console → IAM & Admin → Service Accounts.',
                    'jankx'
                ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(self::OPT_SERVICE_ACCOUNT); ?>">
                            <?php esc_html_e('Service Account JSON', 'jankx'); ?>
                        </label>
                    </th>
                    <td>
                        <?php $hasSavedKey = (bool) get_option(self::OPT_SERVICE_ACCOUNT, ''); ?>
                        <textarea name="<?php echo esc_attr(self::OPT_SERVICE_ACCOUNT); ?>"
                            id="<?php echo esc_attr(self::OPT_SERVICE_ACCOUNT); ?>" rows="8" cols="60" placeholder="<?php echo $hasSavedKey
                                   ? esc_attr(__('(đã lưu — paste JSON mới để thay thế)', 'jankx'))
                                   : esc_attr('{ "type": "service_account", "project_id": "...", ... }'); ?>"
                            class="large-text code"></textarea>
                        <p class="description">
                            <?php esc_html_e(
                                'Để trống để giữ nguyên key hiện tại.',
                                'jankx'
                            ); ?>
                            <?php if ($hasSavedKey): ?>
                                <span style="color:#4caf50;">&#10003; <?php esc_html_e('Đã cấu hình', 'jankx'); ?></span>
                            <?php else: ?>
                                <span style="color:#f44336;">&#9888; <?php esc_html_e('Chưa cấu hình', 'jankx'); ?></span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Đích ghi dữ liệu', 'jankx'); ?></h3>
            <p class="description">
                <?php esc_html_e(
                    'Chỉ định file Google Sheet và tab sẽ nhận dữ liệu đơn hàng.',
                    'jankx'
                ); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(self::OPT_SPREADSHEET_ID); ?>">
                            <?php esc_html_e('Spreadsheet ID', 'jankx'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" name="<?php echo esc_attr(self::OPT_SPREADSHEET_ID); ?>"
                            id="<?php echo esc_attr(self::OPT_SPREADSHEET_ID); ?>"
                            value="<?php echo esc_attr(get_option(self::OPT_SPREADSHEET_ID, '')); ?>" class="regular-text"
                            placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgVE2upms" />
                        <p class="description">
                            <?php esc_html_e(
                                'Chuỗi ID dài trong URL của Google Sheet, nằm giữa /d/ và /edit.',
                                'jankx'
                            ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="<?php echo esc_attr(self::OPT_SHEET_NAME); ?>">
                            <?php esc_html_e('Tên Sheet / Tab', 'jankx'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" name="<?php echo esc_attr(self::OPT_SHEET_NAME); ?>"
                            id="<?php echo esc_attr(self::OPT_SHEET_NAME); ?>"
                            value="<?php echo esc_attr(get_option(self::OPT_SHEET_NAME, 'Orders')); ?>" class="regular-text"
                            placeholder="Orders" />
                        <p class="description">
                            <?php esc_html_e(
                                'Tên tab bên trong file Spreadsheet. Mặc định: Orders.',
                                'jankx'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <hr>
            <h3><?php esc_html_e('Cấu trúc cột', 'jankx'); ?></h3>
            <p class="description">
                <?php esc_html_e(
                    'Dữ liệu đơn hàng sẽ được ghi theo thứ tự cột sau (các extension khác có thể đăng ký thêm cột qua hook `jankx/ggsheet_orders/register_columns`):',
                    'jankx'
                ); ?>
            </p>
            <table class="widefat striped" style="max-width:800px; margin-top:8px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Cột (Vị trí)', 'jankx'); ?></th>
                        <th><?php esc_html_e('ID Cột', 'jankx'); ?></th>
                        <th><?php esc_html_e('Tên cột (Ghi chú)', 'jankx'); ?></th>
                        <th><?php esc_html_e('Độ ưu tiên', 'jankx'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $registry = new ColumnRegistry();
                    $columns = $registry->getColumns();

                    foreach ($columns as $index => $column):
                        // Convert 0-index to Excel column letter (A, B, ..., Z, AA, AB)
                        $colIdx = $index + 1;
                        $letter = '';
                        while ($colIdx > 0) {
                            $modulo = ($colIdx - 1) % 26;
                            $letter = chr(65 + $modulo) . $letter;
                            $colIdx = (int) (($colIdx - $modulo) / 26);
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($letter); ?></strong></td>
                            <td><code><?php echo esc_html($column->getId()); ?></code></td>
                            <td><?php echo esc_html($column->getLabel()); ?></td>
                            <td><?php echo (int) $column->getPriority(); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button(__('Lưu cài đặt', 'jankx')); ?>
        </form>
        <?php
    }

    // -------------------------------------------------------------------------
    // Sanitization
    // -------------------------------------------------------------------------

    /**
     * Validate and sanitize the service account JSON textarea.
     * Returns the existing saved value when the field is left empty so the key
     * is never accidentally cleared by saving another tab.
     *
     * @param string|null $value Raw textarea input.
     * @return string Normalised JSON string, or preserved existing value.
     */
    public function sanitizeServiceAccountJson(string|null $value): string
    {
        if ($value === null) {
            return (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        }

        $value = trim($value);

        if ($value === '') {
            return (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error(
                self::OPT_SERVICE_ACCOUNT,
                'invalid_json',
                __('Service Account JSON không hợp lệ. Vui lòng paste đúng nội dung file JSON.', 'jankx')
            );
            return (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        }

        if (empty($decoded['private_key']) || empty($decoded['client_email'])) {
            add_settings_error(
                self::OPT_SERVICE_ACCOUNT,
                'invalid_sa',
                __('File JSON không phải Service Account key hợp lệ (thiếu private_key hoặc client_email).', 'jankx')
            );
            return (string) get_option(self::OPT_SERVICE_ACCOUNT, '');
        }

        return (string) json_encode($decoded);
    }

    // -------------------------------------------------------------------------
    // Static helpers (used by GGSheetOrdersExtension to read config)
    // -------------------------------------------------------------------------

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
