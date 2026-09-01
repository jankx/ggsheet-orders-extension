# GGSheet Orders Extension

Extension cho **Jankx Theme** tự động đồng bộ đơn hàng vào Google Sheet.

## Chức năng

- **Tạo đơn hàng mới** → append 1 row mới vào Google Sheet và lưu vị trí row
- **Cập nhật / đổi trạng thái đơn hàng** → update đúng row tương ứng (in-place)
- Không cần library bên ngoài — chỉ dùng `openssl` + `wp_remote_*`

## Yêu cầu

- PHP ≥ 7.4
- Extension **base-ecommerce** đã được kích hoạt
- Extension `openssl` của PHP
- Service Account Google Cloud với quyền **Google Sheets API** (`spreadsheets` scope)

## Cài đặt

### 1. Tạo Service Account Google

1. Vào [Google Cloud Console](https://console.cloud.google.com/) → chọn project
2. **APIs & Services → Enable APIs** → bật **Google Sheets API**
3. **IAM & Admin → Service Accounts** → tạo service account mới
4. Tạo JSON key → tải file `.json` về máy
5. Mở Google Sheet file → **Share** → thêm email service account với quyền **Editor**

### 2. Cấu hình trong WordPress

**Settings → GGSheet Orders**:

| Field | Mô tả |
|---|---|
| **Service Account JSON** | Paste toàn bộ nội dung file `.json` key |
| **Spreadsheet ID** | Chuỗi ID trong URL của Google Sheet (giữa `/d/` và `/edit`) |
| **Sheet / Tab Name** | Tên tab trong file (mặc định: `Orders`) |

### 3. Cấu trúc cột Sheet

| A | B | C | D | E | F | G | H | I | J | K | L |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Order Number | Date Created | Status | Customer Name | Customer Email | Customer Phone | Customer Address | Payment Method | Total | Currency | Items | Last Updated |

## Architecture

Extension tuân theo các design pattern:

```
GGSheetOrdersExtension          ← Singleton + AbstractExtension (entry point, WP hooks)
    └── OrderSheetSync          ← Service Layer (use-cases: syncNewOrder, syncOrderUpdate)
            ├── GoogleSheetClientInterface  ← Strategy (transport layer)
            │       └── ServiceAccountSheetClient  ← Concrete: JWT/OAuth2 + REST API v4
            ├── RowIndexRepositoryInterface ← Repository (persist row mapping)
            │       └── WordPressRowIndexRepository ← Concrete: wp_options
            └── OrderDataMapper             ← Data Mapper (Order → flat row array)
```

## Extension Points (WordPress Filters)

### Swap client implementation

```php
// Dùng implementation khác (e.g. OAuth2 user flow)
add_filter('jankx/ggsheet_orders/client', function () {
    return new MyCustomSheetClient();
});
```

### Swap repository implementation

```php
// Lưu row index vào custom table thay vì wp_options
add_filter('jankx/ggsheet_orders/row_index_repository', function () {
    return new MyDatabaseRowIndexRepository();
});
```

### Tùy chỉnh dữ liệu row

```php
// Thêm / bớt cột trước khi ghi vào Sheet
add_filter('jankx/ggsheet_orders/row_data', function (array $row, Order $order) {
    $row[] = $order->getCustomerAddress(); // thêm cột phụ
    return $row;
}, 10, 2);
```

### Action hooks

| Hook | Tham số | Mô tả |
|---|---|---|
| `jankx/ggsheet_orders/after_sync_new_order` | `$order, $rowIndex, $saved` | Sau khi append row mới |
| `jankx/ggsheet_orders/after_sync_order_update` | `$order, $rowIndex, $result` | Sau khi update row |

## Tác giả

Puleeno Nguyen <puleeno@gmail.com>
