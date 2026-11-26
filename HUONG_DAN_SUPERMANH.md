# SUPER MẠNH Shop cho Switch (dựa trên CyberFoil 1.5.1)

App cài game/bản Việt hóa trên Switch, mang thương hiệu SUPER MẠNH, **đồng bộ với plugin SuperManh Switch Shop** trên supermanh.com.

## Khác gì CyberFoil gốc
| | |
|---|---|
| Tên, icon, logo, hình nền, màu | SUPER MẠNH (theme `theme/theme.json` cam đỏ) |
| Thư mục trên thẻ nhớ | `switch/SuperManhShop/` — cài song song với CyberFoil thường, không đè cấu hình nhau |
| Shop mặc định | Tự tạo và chọn sẵn `https://supermanh.com/tinfoil` khi mở lần đầu, khách không phải nhập gì |
| Mã máy | **Luôn gửi `UID`** (SHA-256 của CID chip nhớ eMMC). Bản CyberFoil gốc build không có thư viện đóng `lib.a` sẽ bỏ luôn UID, làm kích hoạt theo máy không chạy. |
| Cập nhật app | Kiểm tra bản mới ở **repo GitHub của bạn**, không phải repo CyberFoil |
| Lời nhắn | Shop trả về "Ma kich hoat: XXXX XXXX…" → app hiện ngay khi mở shop |

Không có `HAUTH/UAUTH` (thuật toán nằm trong thư viện đóng của tác giả CyberFoil). Plugin không cần chúng; chỉ đừng bật **Khoá tên miền** trong plugin nếu khách dùng app này.

## Build bằng GitHub Actions (không cần cài gì trên máy)
1. Tạo repo mới trên GitHub, ví dụ `supermanh-shop-switch` (**Public** — bắt buộc theo giấy phép GPL-3.0 của CyberFoil).
2. Đẩy mã nguồn lên:
   - Có git: giải nén, mở terminal trong thư mục, chạy
     `git remote add origin https://github.com/<tên>/supermanh-shop-switch.git` rồi `git push -u origin supermanh`
   - Hoặc kéo-thả toàn bộ file lên trang GitHub (workflow tự tải lại thư viện giao diện Plutonium nếu bị thiếu).
3. (Tuỳ chọn) Đổi địa chỉ shop: repo → **Settings → Secrets and variables → Actions → Variables** → thêm `SMSS_SHOP_URL` = `https://shop.supermanh.com/tinfoil` (đúng đường dẫn shop trong plugin).
4. Vào tab **Actions** → chờ "Build SUPER MANH Shop" chạy xong (~5–10 phút) → mở lần chạy → tải **SuperManhShop** ở mục Artifacts.
5. Phát hành bản cho khách tự cập nhật: tạo tag **trùng số phiên bản** trong `Makefile` (`APP_VERSION`, hiện là `1.5.1`, không có chữ v) → Actions tạo Release kèm `SuperManhShop.zip`. Lần sau tăng `APP_VERSION` (vd `1.5.2`) rồi tag `1.5.2`.

## Cài lên Switch
Giải nén `SuperManhShop.zip` vào **gốc thẻ nhớ** → có `switch/SuperManhShop/supermanhshop.nro`.
Mở bằng Homebrew Menu (nên mở qua một game, giữ R khi bấm game, để đủ RAM).

Bản NSP (icon ngoài màn hình HOME) cần đóng gói forwarder bằng `prod.keys` của máy bạn — làm sau khi bản NRO đã chạy ổn.

## Giấy phép
GPL-3.0, dựa trên [CyberFoil](https://github.com/luketanti/CyberFoil) của luketanti (Awoo Installer, Tinfoil gốc của Adubbz…). Giữ nguyên các file LICENSE và công khai mã nguồn bản đã sửa.
