# SUPER MẠNH Shop — Hướng dẫn cài đặt trọn bộ

```
SuperManh_TronBo/
├─ 1_Plugin_WordPress/supermanh-switch-shop.zip   ← cài lên supermanh.com
├─ 2_App_Switch_NRO/switch/SuperManhShop/          ← chép vào gốc thẻ nhớ (chạy qua Homebrew Menu)
├─ 3_Dong_goi_NSP/                                 ← tạo file NSP có icon ngoài HOME
│   ├─ DONG_GOI_NSP.bat
│   ├─ dong_goi_nsp.ps1
│   └─ kit/ (exefs, romfs, control)
└─ HUONG_DAN_CAI_DAT.md
```

## Bước 1 — Web supermanh.com (làm 1 lần)
1. WordPress → **Plugins → Add New → Upload Plugin** → chọn `1_Plugin_WordPress/supermanh-switch-shop.zip` → **Activate** (bản cũ: đồng ý thay thế).
2. **Switch Shop → Tổng quan & cài đặt**:
   - Dán **Google API key** (Google Cloud Console → bật *Google Drive API* → tạo API key → giới hạn key chỉ cho Drive API).
   - **Liên hệ nạp điểm**: Zalo / Facebook của bạn.
   - Giữ tick **Kích hoạt theo máy** và **Ẩn shop**. **Không** bật *Khoá tên miền* (app SUPER MẠNH không tạo mã HAUTH).
   - Đường dẫn shop để nguyên `tinfoil` (app đã cài sẵn `https://supermanh.com/tinfoil`).
3. **Thư mục Drive**: dán link thư mục chứa các bản Việt hóa (chia sẻ "Bất kỳ ai có đường liên kết").
4. Điện thoại mở `supermanh.com/sm-quanly` → đăng nhập WordPress → **Thêm vào màn hình chính**. Đặt giá cho từng bản ở tab **Bản Việt hóa**.

## Bước 2 — App trên Switch

### Cách A: NSP (khuyên dùng — có icon ngoài màn hình HOME)
1. Trên **PC Windows**: chép `prod.keys` của máy Switch **bạn** vào thư mục `3_Dong_goi_NSP\`.
2. Nhấp đúp `DONG_GOI_NSP.bat` → lần đầu tự tải hacBrewPack (công cụ đóng gói NSP mã nguồn mở) → ra file `3_Dong_goi_NSP\NSP\SuperManhShop_v1.5.1.nsp`.
3. Cài file NSP bằng Tinfoil / DBI / CyberFoil như cài game. Mã ứng dụng: `05004D414E480000`.
4. Nếu Tinfoil báo lỗi chữ ký: Tinfoil → Options → **Install Unsigned Code = YES**.

> `prod.keys` chỉ được đọc trên PC của bạn, không gửi đi đâu. **Đừng** đưa file này lên GitHub, nhóm chat hay gửi cho người khác. Mỗi khách tự cài NSP bạn đã đóng gói — không cần khoá của họ.

### Cách B: NRO (không cần đóng gói)
Chép thư mục `2_App_Switch_NRO/switch` vào **gốc thẻ nhớ** → mở **SUPER MANH Shop** trong Homebrew Menu (nên mở qua một game, giữ R khi bấm game).

## Bước 3 — Khách dùng như thế nào
1. Mở app SUPER MANH Shop → vào mục shop **SUPER MANH Shop** (đã có sẵn).
2. App hiện **Mã kích hoạt: XXXX XXXX** → khách gửi mã + chuyển khoản cho bạn.
3. Bạn mở app quản lý trên điện thoại → **Kích hoạt mã máy** → nhập mã → Nạp điểm / Tặng bản / Gói thời hạn.
4. Khách tắt hẳn app rồi mở lại → thấy các bản Việt hóa, tải thẳng từ Google Drive.
5. Khách tự mở khoá thêm bản bằng điểm ở `supermanh.com/sm-khach` (mã kích hoạt + PIN web hiện trong app).

## Khi có lỗi
| Hiện tượng | Cách xử lý |
|---|---|
| Không thấy mã kích hoạt | Plugin chưa cài / chưa bật *Kích hoạt theo máy*, hoặc đường dẫn shop khác `/tinfoil` |
| "Khong doc duoc ma may" | Máy không đọc được chip nhớ → tạo tài khoản tên/mật khẩu cho khách trong app quản lý |
| Tải file báo lỗi / quá giới hạn | Google Drive giới hạn lượt tải 1 file/ngày → đợi, hoặc tạo bản sao file trên Drive |
| `DONG_GOI_NSP.bat` báo thiếu khoá | `prod.keys` phải nằm cạnh file .bat và có `header_key` + `key_area_key_application_xx` |
| Windows Defender chặn hacbrewpack.exe | Đây là công cụ mã nguồn mở không ký số; cho phép chạy (Allow) rồi chạy lại |

---
App dựa trên CyberFoil (GPL-3.0) của luketanti. Mã nguồn: https://github.com/tieuvoton123/supermanh
