<?php
namespace Jankx\Extensions\GGSheetOrders;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\Ecommerce\Cart\Cart;
use Jankx\Extensions\Ecommerce\Order\Order;
use Jankx\Extensions\GGSheetOrders\Admin\GGSheetSettingsPage;
use Jankx\Extensions\GGSheetOrders\Client\GoogleSheetClientInterface;
use Jankx\Extensions\GGSheetOrders\Client\ServiceAccountSheetClient;
use Jankx\Extensions\GGSheetOrders\DataMapper\OrderDataMapper;
use Jankx\Extensions\GGSheetOrders\Repository\RowIndexRepositoryInterface;
use Jankx\Extensions\GGSheetOrders\Repository\WordPressRowIndexRepository;
use Jankx\Extensions\GGSheetOrders\Service\OrderSheetSync;

/**
 * GGSheet Orders Extension for Jankx
 *
 * Listens to order lifecycle hooks exposed by the base-ecommerce extension
 * and delegates sync work to the {@see OrderSheetSync} service.
 *
 * Architecture overview:
 *
 *   AbstractExtension (Singleton, lifecycle management)
 *        └── GGSheetOrdersExtension (this class — wires WP hooks)
 *                └── OrderSheetSync          (Service Layer)
 *                        ├── GoogleSheetClientInterface  (Strategy)
 *                        │       └── ServiceAccountSheetClient (Concrete)
 *                        ├── RowIndexRepositoryInterface (Repository)
 *                        │       └── WordPressRowIndexRepository (Concrete)
 *                        └── OrderDataMapper             (Data Mapper)
 *
 * @package Jankx\Extensions\GGSheetOrders
 */
class GGSheetOrdersExtension extends AbstractExtension
{
    /** @var static|null */
    protected static $instance;

    /** @var OrderSheetSync|null Built lazily on first use. */
    private ?OrderSheetSync $syncService = null;

    // -------------------------------------------------------------------------
    // AbstractExtension implementation
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'Jankx\\Extensions\\GGSheetOrders\\';
            $baseDir = __DIR__ . '/src/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // Admin settings page.
        if (is_admin()) {
            (new GGSheetSettingsPage())->register();
        }

        // Hook into base-ecommerce order lifecycle events.
        add_action(
            'jankx/ecommerce/order/created',
            [$this, 'on_order_created'],
            20,   // run after base-ecommerce's own listener (priority 10)
            2
        );

        add_action(
            'jankx/ecommerce/order/status_changed',
            [$this, 'on_order_status_changed'],
            20,
            4
        );

        /**
         * Additional hook for generic order updates (e.g. admin edits).
         * Other extensions can fire `jankx/ecommerce/order/updated` with the
         * Order object as the first argument.
         */
        add_action(
            'jankx/ecommerce/order/updated',
            [$this, 'on_order_updated'],
            20,
            1
        );
    }

    // -------------------------------------------------------------------------
    // Hook callbacks
    // -------------------------------------------------------------------------

    /**
     * Append a new Google Sheet row when an order is first created.
     *
     * @param Order $order Newly created order.
     * @param Cart  $cart  The cart that generated it.
     */
    public function on_order_created(Order $order, Cart $cart): void
    {
        $service = $this->getSyncService();
        if (!$service) {
            return;
        }

        $service->syncNewOrder($order, $cart);
    }

    /**
     * Update the corresponding Google Sheet row when an order's status changes.
     *
     * @param Order  $order     The order whose status changed.
     * @param string $newStatus New status slug.
     * @param string $oldStatus Previous status slug.
     * @param int    $handlerId ID of the user who made the change.
     */
    public function on_order_status_changed(Order $order, string $newStatus, string $oldStatus, int $handlerId): void
    {
        $service = $this->getSyncService();
        if (!$service) {
            return;
        }

        $service->syncOrderUpdate($order);
    }

    /**
     * Update the corresponding Google Sheet row when an order is edited.
     *
     * @param Order $order The updated order.
     */
    public function on_order_updated(Order $order): void
    {
        $service = $this->getSyncService();
        if (!$service) {
            return;
        }

        $service->syncOrderUpdate($order);
    }

    // -------------------------------------------------------------------------
    // Service factory (lazy construction + filter extension point)
    // -------------------------------------------------------------------------

    /**
     * Build (or return cached) the OrderSheetSync service.
     *
     * All dependencies are resolved here:
     *   - GoogleSheetClientInterface via `jankx/ggsheet_orders/client` filter
     *   - RowIndexRepositoryInterface via `jankx/ggsheet_orders/row_index_repository` filter
     *   - Spreadsheet settings from GGSheetSettingsPage options
     *
     * Returns null when the extension is not yet configured so that hooks
     * silently skip rather than throwing errors.
     */
    private function getSyncService(): ?OrderSheetSync
    {
        if ($this->syncService !== null) {
            return $this->syncService;
        }

        $client = $this->resolveClient();
        if (!$client) {
            return null;
        }

        $repository = $this->resolveRepository();
        $mapper = new OrderDataMapper();
        $spreadsheetId = GGSheetSettingsPage::getSpreadsheetId();
        $sheetName = GGSheetSettingsPage::getSheetName();

        $this->syncService = new OrderSheetSync(
            $client,
            $repository,
            $mapper,
            $spreadsheetId,
            $sheetName
        );

        return $this->syncService;
    }

    /**
     * Resolve the concrete GoogleSheetClientInterface implementation.
     *
     * Third-party code can replace the default ServiceAccountSheetClient by
     * hooking into `jankx/ggsheet_orders/client`:
     *
     *   add_filter('jankx/ggsheet_orders/client', function () {
     *       return new MyCustomSheetClient();
     *   });
     *
     * @return GoogleSheetClientInterface|null
     */
    private function resolveClient(): ?GoogleSheetClientInterface
    {
        /** @var GoogleSheetClientInterface|null $client */
        $client = apply_filters('jankx/ggsheet_orders/client', null);

        if ($client instanceof GoogleSheetClientInterface) {
            return $client;
        }

        // Default: Service Account JWT client.
        $serviceAccount = GGSheetSettingsPage::getServiceAccount();
        if (!$serviceAccount) {
            // Not yet configured — skip silently.
            return null;
        }

        try {
            return new ServiceAccountSheetClient($serviceAccount);
        } catch (\InvalidArgumentException $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[GGSheetOrders] Failed to instantiate ServiceAccountSheetClient: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Resolve the concrete RowIndexRepositoryInterface implementation.
     *
     * Third-party code can replace the default WordPressRowIndexRepository by
     * hooking into `jankx/ggsheet_orders/row_index_repository`:
     *
     *   add_filter('jankx/ggsheet_orders/row_index_repository', function () {
     *       return new MyDatabaseRowIndexRepository();
     *   });
     *
     * @return RowIndexRepositoryInterface
     */
    private function resolveRepository(): RowIndexRepositoryInterface
    {
        /** @var RowIndexRepositoryInterface|null $repo */
        $repo = apply_filters('jankx/ggsheet_orders/row_index_repository', null);

        if ($repo instanceof RowIndexRepositoryInterface) {
            return $repo;
        }

        return new WordPressRowIndexRepository();
    }
}
