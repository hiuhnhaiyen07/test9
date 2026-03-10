# Premium Service Website

Bộ code này là website dịch vụ số dạng file PHP + HTML, có sẵn:

- Trang khách nhập Gmail
- Trang payment hiển thị QR, số tiền, nội dung chuyển khoản
- Webhook xác nhận thanh toán tự động
- Gửi Telegram cho admin khi thanh toán thành công
- Admin dashboard có đăng nhập
- Lịch sử đơn hàng, lọc đơn, cập nhật trạng thái
- Chống spam cơ bản ở form tạo đơn
- Upload QR mới ngay trong trang quản trị
- Giao diện premium với hiệu ứng nền, sticker và confetti

## Cấu trúc chính

- `index.html` — landing page / nhập Gmail
- `payment.html` — trang thanh toán QR
- `success.html` — trang xác nhận thanh toán thành công
- `admin.html` — trang quản trị
- `css/style.css` — style giao diện khách
- `css/admin.css` — style giao diện admin
- `js/app.js` — xử lý trang khách
- `js/admin.js` — xử lý dashboard admin
- `api/create_order.php` — tạo đơn hàng
- `api/order_status.php` — kiểm tra trạng thái đơn
- `api/webhook.php` — nhận callback thanh toán
- `api/public_config.php` — cấp cấu hình công khai cho frontend
- `api/admin_*.php` — API quản trị
- `api/send_telegram.php` — test gửi Telegram từ dashboard
- `api/config.php` — cấu hình gốc
- `data/settings.json` — file sinh ra khi lưu cấu hình trong admin

## Đăng nhập admin mặc định

- URL: `admin.html`
- Mật khẩu mặc định: `admin123456`

Sau khi đăng nhập, hãy vào tab **Cấu hình & QR** để đổi ngay mật khẩu admin mới.

## Chạy local

```bash
cd goi-dich-vu-telegram-site
php -S 127.0.0.1:8000
```

Mở các trang:

- `http://127.0.0.1:8000/index.html`
- `http://127.0.0.1:8000/admin.html`

## Cấu hình Telegram

Có 2 cách:

### Cách 1: sửa trực tiếp trong `api/config.php`

```php
'telegram_bot_token' => 'BOT_TOKEN_HERE',
'telegram_chat_id' => 'CHAT_ID_HERE',
'webhook_secret' => 'CHANGE_ME_SECRET',
```

### Cách 2: đăng nhập admin rồi lưu trong tab cấu hình

Các giá trị lưu từ dashboard sẽ được ghi vào `data/settings.json` và ghi đè lên cấu hình gốc.

## Cách webhook hoạt động

File `api/webhook.php` nhận JSON POST, kiểm tra:

1. Secret hợp lệ qua header `X-Webhook-Secret` hoặc trường `secret`
2. Tìm `order_id` từ payload hoặc từ nội dung chuyển khoản
3. Kiểm tra số tiền
4. Đổi trạng thái sang `paid`
5. Gửi Telegram cho admin

### Ví dụ gọi webhook bằng curl

```bash
curl -X POST http://127.0.0.1:8000/api/webhook.php \
  -H "Content-Type: application/json" \
  -H "X-Webhook-Secret: CHANGE_ME_SECRET" \
  -d '{
    "amount": 29000,
    "content": "NAP OD20260310194717FCB0",
    "transaction_id": "MB123456789"
  }'
```

## Chống spam đang có

`api/create_order.php` đang dùng các lớp chống spam cơ bản:

- Giới hạn số lần tạo đơn theo IP mỗi phút
- Giới hạn số lần tạo đơn theo IP mỗi giờ
- Honeypot ẩn ở form (`website`)
- Kiểm tra thời gian điền form tối thiểu
- Chặn tạo đơn lặp quá gần nhau với cùng Gmail/IP

## Upload QR trong admin

Tại tab **Cấu hình & QR**:

- Chọn ảnh PNG/JPG/WEBP
- Bấm lưu cấu hình
- Ảnh mới sẽ được lưu vào thư mục `uploads/`
- Trang payment tự cập nhật ảnh QR mới

## Lưu ý triển khai

- Thư mục `data/` và `uploads/` phải có quyền ghi
- Website dùng lưu trữ dạng file JSON, phù hợp cho quy mô nhỏ đến vừa
- Nếu cần tải lớn hơn, nên chuyển sang MySQL hoặc SQLite
- Trang khách chỉ thu Gmail, không thu mật khẩu

## Gợi ý nâng cấp thêm

- Thêm export CSV đơn hàng
- Thêm phân quyền nhiều admin
- Thêm đăng nhập 2 lớp cho dashboard
- Thêm dark/light theme chuyển đổi trong admin
- Chuyển lưu trữ sang MySQL để tối ưu truy vấn lớn
