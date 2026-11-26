# SUPER MẠNH Test Host — chạy kho thử trên PC

Tool này biến PC thành một kho nhỏ trong mạng nhà, để test app **SUPER MẠNH Việt Hóa** (hoặc Tinfoil / CyberFoil) mà không cần web:
- File trên PC → Switch tải thẳng qua Wi-Fi, có tải tiếp khi rớt mạng.
- Link tải trực tiếp (http/https hoặc `gdrive:ID`) ghi trong `links.txt`.
- Có giả lập **mã kích hoạt theo máy** giống web thật.

## 1. Chạy trên PC
1. Nhấp đúp **`CHAY_TEST_HOST.bat`**. Có sẵn `SuperManh_TestHost.exe` nên không cần cài Python.
2. Windows hỏi tường lửa → bấm **Allow** (chọn mạng Private).
3. Cửa sổ hiện địa chỉ kho, ví dụ `http://192.168.1.10:8000/tinfoil`. Ghi lại địa chỉ này.

Lần chạy đầu, tool tự tạo bên cạnh nó:
| File / thư mục | Dùng để |
|---|---|
| `files\` | Chép file `.nsp / .nsz / .xci / .xcz` vào đây (được chia thư mục con) |
| `links.txt` | Mỗi dòng một link: `link \| Tên file [TitleID][v0].nsp \| dung lượng (không bắt buộc)` |
| `config.json` | `port` (8000), `activation` (true/false), `title`, `contact` |
| `devices.json` | Danh sách máy Switch đã kết nối, tool tự ghi |

Sửa file xong không cần chạy lại tool; riêng `port` thì phải chạy lại.

## 2. Cho app SUPER MẠNH Việt Hóa nối vào máy test
1. Tạo file **`test_host.txt`** trong thư mục **`switch/SuperManhShop/`** trên thẻ nhớ. Nội dung là một dòng địa chỉ kho, ví dụ:
   ```
   http://192.168.1.10:8000/tinfoil
   ```
2. Tắt hẳn app rồi mở lại → chọn **Kho Việt hóa**. App giờ nối vào PC.
3. Test xong: **xoá `test_host.txt`**, mở lại app là về kho thật supermanh.com.

Dùng Tinfoil thì thêm kho như thường: File Browser → (-) → Protocol `http`, Host là IP của PC, Port `8000`, Path `/tinfoil`.

## 3. Test bước kích hoạt
1. Mở Kho Việt hóa trên Switch → app hiện **Mã kích hoạt: XXXX XXXX**. Cửa sổ tool cũng in ra `MAY MOI: ma kich hoat ...`.
2. Trên PC (hoặc điện thoại cùng Wi-Fi) mở **`http://localhost:8000/quanly`** → bấm **Kich hoat** ở dòng mã đó.
3. Tắt hẳn app, mở lại → thấy danh sách file và tải được.

Chỉ muốn test tải: mở `config.json`, đặt `"activation": false`.

## Lỗi hay gặp
| Hiện tượng | Cách xử lý |
|---|---|
| Switch không kết nối được | PC và Switch phải chung Wi-Fi/mạng; đã bấm Allow tường lửa; thử tắt VPN trên PC |
| Báo "Khong doc duoc ma may" | Trình cài không gửi mã máy → đặt `"activation": false` |
| Cửa sổ báo không mở được cổng | Đổi `port` trong `config.json` (ví dụ 8080), nhớ sửa luôn địa chỉ trong `test_host.txt` |
| App vẫn vào supermanh.com | Kiểm tra đúng tên `test_host.txt`, đúng thư mục `switch/SuperManhShop/`, rồi tắt hẳn app mở lại |

Tool chỉ để test trong mạng nhà, không mở cổng ra Internet.
