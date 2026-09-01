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

Vào **Ecommerce → Cài đặt chung → tab Google Sheet** trong wp-admin để cấu hình.

| Field | Mô tả |
|---|---|
| **Service Account JSON** | Paste toàn bộ nội dung file `.json` key |
| **Spreadsheet ID** | Chuỗi ID trong URL của Google Sheet (giữa `/d/` và `/edit`) |
| **Sheet / Tab Name** | Tên tab trong file (mặc định: `Orders`) |

Cấu trúc cột không bị fix cứng (hardcode) mà được quản lý qua `ColumnRegistry`. Các cột mặc định chiếm priority từ `10` đến `230`. Bạn có thể dễ dàng xem cấu trúc cột thực tế (đã bao gồm cột do các extension khác inject) tại trang cài đặt.

## Architecture

Extension tuân theo các design pattern:

```
GGSheetOrdersExtension          ← Singleton + AbstractExtension
    ├── OrderSheetSync          ← Service Layer
    │       ├── GoogleSheetClientInterface  ← Strategy (transport)
    │       ├── RowIndexRepositoryInterface ← Repository (persist row mapping)
    │       └── OrderDataMapper             ← Data Mapper (dựa trên ColumnRegistry)
    │
    └── ColumnRegistry          ← Registry (Quản lý các cột linh hoạt)
```

## Dành cho Developer (Tích hợp Extension khác)

Để giữ cấu trúc dữ liệu không bị phá vỡ khi nhiều extension muốn ghi đè, hãy dùng **Action Hook** `jankx/ggsheet_orders/register_columns` thay vì filter array.

Ví dụ, `flexible-tour-pricing` muốn thêm các cột liên quan đến giá thay đổi:

```php
add_action('jankx/ggsheet_orders/register_columns', function ($registry) {
    // Adds a column right after Total (priority 210)
    $registry->addColumn(
        'adult_count', 
        __('Số NL', 'jankx'), 
        211, 
        function ($order) {
            // Your logic to extract data from the $order here
            return 2; 
        }
    );

    // Adds a column near the end (priority 800)
    $registry->addColumn(
        'tour_date', 
        __('Ngày khởi hành', 'jankx'), 
        800, 
        function ($order) {
            return get_post_meta($order->getId(), '_tour_date', true);
        }
    );
});
```

Hệ thống sẽ tự động tổng hợp, sắp xếp ưu tiên dựa theo tham số `priority`, và push sang Google Sheets chính xác. Không cần lo độ lệch cột!

### Action hooks

| Hook | Tham số | Mô tả |
|---|---|---|
| `jankx/ggsheet_orders/after_sync_new_order` | `$order, $rowIndex, $saved` | Sau khi append row mới |
| `jankx/ggsheet_orders/after_sync_order_update` | `$order, $rowIndex, $result` | Sau khi update row |

## Tác giả

Puleeno Nguyen <puleeno@gmail.com>
