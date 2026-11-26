# SuperManh Switch Shop — plugin WordPress (v1.6.1)

Biến supermanh.com thành shop cho Tinfoil. Máy Switch **tải thẳng từ Google Drive** (hoặc link bạn tự điền), web chỉ phát danh sách game, không tốn băng thông web, không cần PC bật.

## Kích hoạt theo máy (bản 1.5, bật sẵn)
```
Khách thêm shop vào Tinfoil (KHÔNG cần tài khoản, Username/Password để trống)
   → mở Tinfoil: hiện "Ma kich hoat: XXXX XXXX" (riêng từng máy, cài lại Tinfoil vẫn giữ mã)
   → khách gửi mã + chuyển khoản cho bạn
   → bạn mở app → "Kích hoạt mã máy" → nhập mã → chọn: Nạp điểm / Tặng bản / Gói thời hạn xem tất cả
   → khách tắt hẳn Tinfoil, mở lại: thấy bản Việt hóa + điểm + hạn dùng
```
- **Máy chưa kích hoạt:** chỉ thấy các bản miễn phí, kèm lời nhắn gửi mã. **Hết hạn:** danh sách trống và nhắc gia hạn.
- **Gói thời hạn:** 1, 3, 6 tháng, 1 năm hoặc vĩnh viễn, cộng dồn từ ngày hết hạn hiện tại.
- **Trang khách:** khách đăng nhập bằng mã kích hoạt, không cần mật khẩu, để xem điểm và tự mở khoá bản.
- **Tài khoản kiểu cũ** (tên đăng nhập và mật khẩu) vẫn dùng song song được.
- **Chống spam:** một mạng chỉ tạo được tối đa 10 máy mới mỗi giờ.
- Tắt chế độ này: Cài đặt → Bảo mật → bỏ tick "Kích hoạt theo máy".

### Bảo mật mã máy (bản 1.6)
- **Mã duy nhất:** mã do server tạo và lưu, có kiểm tra trùng nên không hai máy nào chung mã. Mỗi lần Tinfoil mở shop, server tra lại máy theo mã riêng của máy (Uid) và kiểm tra lại hạn dùng, điểm, trạng thái khoá. Không có gì lưu sẵn ở phía máy.
- **Mã máy:** Tinfoil và CyberFoil gửi `UID`. Với CyberFoil, đây là SHA-256 của CID chip nhớ eMMC, nên gắn với phần cứng và không đổi khi cài lại. Server không bao giờ hiện UID ra ngoài: mã kích hoạt được tính từ UID cộng một khoá bí mật trên server, nên biết mã cũng không suy ra được UID để giả máy.
- **PIN web:** trang khách giờ cần **mã kích hoạt + PIN 6 số**. PIN chỉ hiện trong Tinfoil trên chính máy đó và đổi sau mỗi 24 giờ. Người chỉ biết mã kích hoạt không đăng nhập được. Link riêng ("Gửi TK") vẫn vào thẳng như trước.

## Bán bản Việt hóa bằng điểm
```
Khách chuyển khoản cho bạn (bạn tự lo)
   → bạn mở app SM Quản lý trên điện thoại → Nạp điểm cho khách
   → khách vào trang của họ (/sm-khach) → bấm "Mở" bản Việt hóa muốn mua → trừ điểm
   → bản đó hiện ngay trong Tinfoil của khách
```
- **App quản lý:** `supermanh.com/sm-quanly`. Đăng nhập WordPress, sau đó vào Menu **Khác → Cài app lên màn hình chính**.
  - **Tổng quan:** điểm nạp trong tháng, lượt mở khoá, hoạt động gần đây.
  - **Khách:** tạo khách (có thể nạp điểm luôn), nạp hoặc trừ điểm, tặng bản (không trừ điểm), gỡ bản (có hoặc không hoàn điểm), gia hạn, khoá tài khoản, đổi mật khẩu, nút **Gửi TK** soạn sẵn tin nhắn gửi khách qua Zalo.
  - **Bản Việt hóa:** đặt giá, đánh dấu miễn phí, ẩn hoặc hiện, thêm link, quét Drive.
- **Trang khách:** `supermanh.com/sm-khach`. Khách đăng nhập bằng tài khoản shop, hoặc mở link có sẵn trong tin nhắn **Gửi TK** là tự đăng nhập.
  - Xem số dư, mở khoá bản Việt hóa, hướng dẫn cài Tinfoil (có nút Chép), lịch sử điểm.
- **Ai thấy bản nào trong Tinfoil:**
  - Khách thường: bản **miễn phí** + bản **đã mở khoá**. Chưa có bản nào thì Tinfoil hiện lời nhắn kèm link trang khách.
  - Tài khoản bật **"Xem tất cả"**: thấy mọi bản. Tài khoản tạo từ bản 1.0 được bật sẵn chế độ này.
- Trừ điểm được làm nguyên tử: khách bấm hai lần liên tiếp cũng không bị trừ hai lần hay xuống số âm. Mọi lần nạp, trừ, mở khoá, hoàn điểm đều ghi lịch sử.

## Cài đặt
1. WordPress → Plugins → Add New → Upload Plugin → chọn `supermanh-switch-shop.zip` → Activate.
2. Menu bên trái xuất hiện **Switch Shop**.

## Tạo Google API key (5 phút, miễn phí)
1. Vào https://console.cloud.google.com → tạo project (tên tuỳ ý).
2. **APIs & Services → Library** → tìm **Google Drive API** → Enable.
3. **APIs & Services → Credentials → Create credentials → API key** → copy key.
4. Bấm vào key vừa tạo → **API restrictions → Restrict key → chọn Google Drive API** → Save.
5. Dán key vào **Switch Shop → Tổng quan & cài đặt → Google API key**.

## Thêm game
- **Cả thư mục (khuyên dùng):** chia sẻ thư mục trên Drive ở chế độ **"Bất kỳ ai có đường liên kết – Người xem"**, rồi dán link thư mục vào **Thư mục Drive**. Plugin quét cả thư mục con và tự cập nhật mỗi giờ.
- **Từng link:** dán vào **Danh sách game → Thêm link**, mỗi dòng một link:
  ```
  https://drive.google.com/file/d/ID/view?usp=sharing
  https://host-khac.com/game.nsz | Tên game [0100XXXXXXXXXXXX][v0].nsz | 5.2G
  ```
  Phần `| tên | dung lượng` không bắt buộc với link Drive (khi đã có API key).
- Tên file nên có `[TitleID][vVersion]` để Tinfoil hiện ảnh và tên game.

## Tài khoản
- **Tài khoản:** tạo tên đăng nhập và mật khẩu cho từng người. Có thể đặt **ngày hết hạn** (bấm "+30 ngày" để gia hạn) và **số máy tối đa** (1 = khoá vào máy Switch đầu tiên đăng nhập).
- Mật khẩu không được chứa `@ & / ? # =` vì Tinfoil không gửi được các ký tự này.
- Sai mật khẩu 15 lần trong 15 phút thì IP đó bị chặn tạm thời.

## Trên Switch (Tinfoil)
File Browser → nhấn **(-)** → điền đúng thông số hiện ở trang **Tổng quan**:
Protocol `https` · Host `supermanh.com` · Port `443` · Path `/tinfoil` · Username/Password.

## Ẩn shop khỏi Google và người lạ (bật sẵn)
- **Ẩn shop:** Googlebot, các bot khác và trình duyệt vào link shop chỉ thấy trang **"Không tìm thấy" (404)** bình thường của web, giống hệt một trang không tồn tại. Chỉ 3 trường hợp được vào: Tinfoil, người có tài khoản và mật khẩu, hoặc link riêng hợp lệ.
- `robots.txt` tự thêm dòng `Disallow: /tinfoil`, nên Google không ghé đường dẫn này. Lưu ý: dòng này chỉ có tác dụng khi bạn không có file `robots.txt` thật trên hosting. Nếu có, hãy tự thêm dòng đó vào file.
- Mọi phản hồi của shop đều kèm `noindex, nofollow, noarchive, nosnippet` (không lập chỉ mục, không lưu bản sao) và `no-referrer` (không gửi địa chỉ shop sang trang khác).
- **Nên đổi Đường dẫn shop** thành chuỗi khó đoán, ví dụ `sm-7k2q`, thay cho `tinfoil`.
- **Tên miền shop:** điền ví dụ `shop.supermanh.com` để Tinfoil và link riêng dùng tên miền này. Subdomain phải trỏ về **cùng hosting WordPress** (thêm Alias hoặc Addon Domain trên hosting) và có SSL.

## Mới ở bản 1.2 (tham khảo Ownfoil, TinShop, TinfoilWebServer)
- **Khoá tên miền** (Cài đặt → Nâng cao, mặc định tắt): Tinfoil chỉ nhận shop từ đúng địa chỉ của bạn, và plugin ghi nhận mã Hauth mà Tinfoil gửi cho tên miền đó ở lần đăng nhập đầu rồi yêu cầu đúng mã về sau. Cần HTTPS. Bật xong hãy tự vào shop bằng Tinfoil trước tiên. Nếu khách thật bị báo lỗi địa chỉ thì tick "Xoá mã Tinfoil đã ghi nhận".
- **Website sau Cloudflare / proxy:** tick mục này nếu web dùng Cloudflare. Không tick thì mọi khách bị tính chung một IP, một người gõ sai mật khẩu nhiều lần có thể làm tất cả bị chặn tạm.
- **Tên hiện trong Tinfoil:** trong app, mở một bản Việt hóa và điền tên muốn hiện (ví dụ "Tên game (Việt hóa SUPER MẠNH)").
- **Đỡ tốn băng thông:** danh sách không đổi thì máy chủ trả lời "không đổi" (304) thay vì gửi lại toàn bộ.

## Tốc độ (bản 1.3)
- Tinfoil tải danh sách nhanh hơn khoảng 3 lần: mật khẩu đúng được nhớ 10 phút nên không phải kiểm tra lại (việc kiểm tra mật khẩu vốn cố ý chậm để chống dò), cài đặt đọc một lần, "lần cuối vào shop" ghi mỗi 5 phút thay vì mỗi lượt.
- Quét lại thư mục Drive chỉ ghi những file thay đổi: 3.000 file không đổi từ hơn 9.000 truy vấn còn 6.
- Muốn nhanh hơn nữa thì phần còn lại là thời gian WordPress khởi động: dùng PHP 8.1 trở lên có OPcache, bật object cache (Redis/Memcached) nếu hosting có, và nhớ loại đường dẫn shop khỏi cache trang.

## Nhập danh sách, tên game và ảnh bìa (bản 1.4)
- **App → Bản Việt hóa → Nhập DS:** chọn file `.json` hoặc `.csv`, hoặc dán nội dung. File mẫu: `mau_nhap.json`, `mau_nhap.csv` (có sẵn trong thư mục plugin, hoặc bấm "File mẫu" trong app).
- **Cột:** `link` (bắt buộc: file Drive, thư mục Drive hoặc link tải trực tiếp), `name`, `title_id`, `price`, `free` (1 = miễn phí), `display` (tên hiện trong Tinfoil), `size`.
  - Tên file chưa có `[TitleID]` thì plugin tự gắn từ cột `title_id`.
  - Nhập lại cùng link thì cập nhật, không tạo trùng.
- **Tên game và ảnh bìa** tự tra theo Title ID từ danh sách tên game công khai đi kèm plugin (hơn 26.000 game, chỉ gồm Title ID, tên và link ảnh). Bản update (đuôi 800) và DLC cũng tra ra game gốc. "Tên hiện trong Tinfoil" tự điền dạng "Tên game (Việt hóa)" nếu bạn để trống.
- Ảnh bìa hiện trong app quản lý và trang khách. Ảnh được tải thẳng từ máy chủ ảnh của eShop, plugin chỉ lưu link.
- **Xuất JSON:** tải toàn bộ danh sách hiện có về máy để sao lưu hoặc sửa hàng loạt rồi nhập lại. **Tra lại tên:** tra lại toàn bộ khi bạn sửa Title ID.

## Lưu ý
- Nếu host dùng **PHP-FastCGI/CGI** mà Tinfoil báo sai mật khẩu dù nhập đúng, thêm vào đầu `.htaccess`:
  ```
  RewriteEngine On
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
  ```
  Hoặc dùng **Link riêng** của tài khoản (trang Tài khoản), link này không cần mật khẩu.
- Nếu dùng plugin cache (LiteSpeed, WP Rocket, W3TC…), thêm `/tinfoil` vào danh sách **không cache**. Plugin đã gửi header no-cache, nhưng một số cache vẫn nên loại trừ thủ công.
- Cron WordPress chạy khi có người vào web. Muốn quét Drive đúng giờ thì bật cron thật trên hosting.
- Google có thể tạm khoá lượt tải một file khi file đó bị tải quá nhiều trong 24 giờ. Khi gặp lỗi này, đợi rồi thử lại, hoặc tạo bản sao file trên Drive (bản sao có ID mới, sẽ được quét lại).
- API key được gửi kèm danh sách game cho người đã đăng nhập, để Tinfoil tải file Drive. Vì vậy hãy giới hạn key **chỉ cho Google Drive API** như bước 4 ở trên.
