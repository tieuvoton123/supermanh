#!/usr/bin/env python3
"""Build romfs/lang/vi.json from en.json (SUPER MANH Vietnamese UI).

Every key of en.json must get a translation here, so a new upstream string
fails loudly instead of silently showing English. Button glyphs (U+E0xx) must match en.json
string by string; the check at the end enforces it.
"""
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
A, B, X, Y = '', '', '', ''
L, R, ZL, ZR = '', '', '', ''
PLUS, MINUS = '', ''
APP = 'SUPER MẠNH Việt Hóa'
KHO = 'Kho Việt hóa SUPER MẠNH'
S = '    '

VI = {
    'main.menu.sd': 'Cài từ thẻ nhớ SD',
    'main.menu.hdd': 'Cài từ ổ USB SSD/HDD',
    'main.menu.net': 'Cài qua mạng LAN / Internet',
    'main.menu.remote': 'Kho Việt hóa',
    'main.menu.guide': 'Hướng dẫn kết nối',
    'main.menu.usb': 'Cài từ NS-USBloader',
    'main.menu.mtp': 'Cài qua MTP (cắm máy tính)',
    'main.menu.backup': 'Sao lưu dữ liệu save',
    'main.menu.sig': 'Quản lý signature patches',
    'main.menu.set': 'Cài đặt',
    'main.menu.exit': 'Thoát',
    'main.net.title': 'Chưa có kết nối mạng',
    'main.net.desc': 'Kiểm tra đã tắt chế độ máy bay và máy đang kết nối Wi-Fi.',
    'main.usb.warn.title': 'Lưu ý!',
    'main.usb.warn.desc': 'Cài qua USB có thể không ổn định trên một số máy và cách kết nối.\nNếu gặp lỗi khi cài qua USB, nên dùng NS-USBloader, hoặc cài qua\nLAN/Internet, nhất là khi có bộ chuyển đổi mạng dây (ethernet).\n\nBạn đã được nhắc trước...',
    'main.usb.warn.opt1': 'Không nhắc lại',
    'main.usb.error.title': 'Chưa nhận kết nối USB',
    'main.usb.error.desc': 'Cắm vào thiết bị tương thích để cài qua USB',
    'main.hdd.title': 'Chưa nhận ổ USB',
    'main.hdd.notfound': 'Cắm ổ USB SSD hoặc ổ cứng để cài từ đó.',
    'main.applet.title': 'Không hỗ trợ chế độ Applet',
    'main.applet.desc': f'{APP} có thể bị lỗi khi chạy ở chế độ Applet (Album).\nHãy mở {APP} qua một game đã cài (giữ R khi bấm vào game)!',
    'main.buttons': f'{A} Chọn{S}{B} Thoát',
    'main.info.button': 'Thông tin',
    'main.info.remote': f'Mở {KHO}: xem mã kích hoạt và cài các bản Việt hóa của bạn.',
    'main.guide.title': f'Cách kết nối {KHO}',
    'main.guide.desc': ('1. Kết nối Wi-Fi có Internet (Wi-Fi quán cà phê, khách sạn phải\n   đăng nhập trên web thì không dùng được).\n'
                        '2. Ngày giờ của máy phải đúng (Cài đặt hệ thống > Ngày giờ).\n'
                        '3. Chọn ô "Kho Việt hóa". Lần đầu sẽ hiện\n   MÃ KÍCH HOẠT dạng XXXX XXXX.\n'
                        '4. Gửi mã kích hoạt cho SUPER MẠNH ({host}) để được kích hoạt.\n'
                        '5. Khi được báo đã kích hoạt: bấm "Kiểm tra lại" là thấy danh sách,\n   chọn bản cần dùng rồi tải.\n'
                        '6. Mở thêm bản bằng điểm tại {host}/sm-khach\n   (mã kích hoạt + PIN web hiện trong thông báo của kho).\n\n'
                        'Địa chỉ kho: {shop}'),
    'main.info.sd': 'Cài file NSP/NSZ/XCI/XCZ có trong thẻ nhớ SD.',
    'main.info.hdd': 'Cài file trực tiếp từ ổ USB SSD hoặc ổ cứng đang cắm.',
    'main.info.mtp': 'Cắm máy tính qua USB và cài bằng cách chép file vào thư mục MTP Install.',
    'main.info.usb': 'Cài qua USB từ công cụ như NS-USBloader.',
    'main.info.net': 'Cài file từ mạng LAN hoặc Internet.',
    'main.info.sig': 'Cài, cập nhật hoặc gỡ signature patches.',
    'main.info.set': f'Thay đổi cài đặt {APP} và kho.',
    'main.info.backup': f'Tải toàn bộ save trên máy của người dùng đã chọn lên kho chỉ với một thao tác.\n\nCách hoạt động:\n1. Nếu máy có nhiều người dùng, chọn tài khoản cần sao lưu.\n2. Nếu chưa cài kho, {APP} thử các kho đã lưu trước, rồi hỏi địa chỉ kho.\n3. Nhập ghi chú cho bản sao lưu (bắt buộc).\n4. {APP} quét save trên máy của người dùng đó và tải từng cái lên máy chủ.\n5. Tiến trình hiện trong lúc quét và tải lên.\n6. Cuối cùng hiện tổng kết số save tải lên thành công và thất bại.',
    'main.info.exit': f'Đóng {APP} và về màn hình HOME.',

    'inst.net.help.title': 'Hướng dẫn',
    'inst.net.help.desc': f'Có thể cài file từ thiết bị khác qua mạng bằng công cụ như NS-USBloader\n(chế độ Tinfoil). Mở phần mềm đó trên máy tính hoặc điện thoại, nhập địa chỉ IP\ncủa Switch (hiện trên màn hình), chọn file rồi gửi sang máy! Nếu phần mềm\nkhông cho chọn loại file, thử đổi đuôi file sang loại nó chấp nhận.\n{APP} không quan tâm đuôi file khi cài qua mạng!\n\nNếu vẫn không làm được, chép file vào thẻ nhớ SD rồi chọn\n"Cài từ thẻ nhớ SD" ở menu chính!',
    'inst.net.src.title': 'Bạn muốn cài từ đâu?',
    'inst.net.src.opt0': 'Đường link (URL)',
    'inst.net.src.opt1': 'Google Drive',
    'inst.net.url.hint': 'Nhập địa chỉ Internet của file',
    'inst.net.url.invalid': 'Đường link không hợp lệ!',
    'inst.net.url.source_string': ' từ đường link',
    'inst.net.gdrive.hint': 'Nhập ID của file Google Drive công khai',
    'inst.net.gdrive.alt_name': 'File Google Drive',
    'inst.net.gdrive.source_string': ' từ Google Drive',
    'inst.net.top_info': 'Chọn các file cần cài từ máy chủ, rồi bấm nút +!',
    'inst.net.top_info1': 'Đang chờ kết nối... Địa chỉ IP của Switch: ',
    'inst.net.failed': 'Cài qua mạng thất bại!',
    'inst.net.transfer_interput': 'Lỗi trong lúc truyền dữ liệu. Kiểm tra kết nối mạng.',
    'inst.net.retry.title': 'Tải thất bại',
    'inst.net.retry.desc': 'Đã thử lại nhiều lần nhưng vẫn lỗi. Thử lại lần nữa?',
    'inst.net.retry.yes': 'Thử lại',
    'inst.net.retry.no': 'Huỷ',
    'inst.net.source_string': ' qua mạng nội bộ',
    'inst.net.buttons': f'{Y} Cài qua Internet{S}{X} Hướng dẫn{S}{B} Huỷ',
    'inst.net.buttons1': f'{A} Chọn file{S}{Y} Chọn tất cả{S}{PLUS} Cài file{S}{B} Huỷ',

    'inst.remote.loading': 'Đang tải danh sách kho...',
    'inst.remote.top_info': 'Chọn các bản cần cài từ kho, rồi bấm nút +! Mục:',
    'inst.remote.failed': 'Không tải được kho',
    'inst.remote.unreachable': 'Không kết nối được kho.',
    'inst.remote.encrypted_unsupported': 'Kho trả về dữ liệu mã hoá, bản này không đọc được. Tắt "Encrypt" trong cài đặt kho.',
    'inst.remote.empty': 'Kho chưa có bản nào cho máy này.',
    'inst.remote.source_string': ' từ kho',
    'inst.remote.buttons_loading': f'{B} Huỷ',
    'inst.remote.buttons': f'{A} Chọn{S}{Y} Chọn tất cả{S}{PLUS} Cài{S}{X} Tải lại{S}{L}/{R} Mục{S}{MINUS} Kiểu xem{S}{B} Huỷ',
    'inst.remote.buttons_all': f'{A} Chọn{S}{Y} Chọn tất cả{S}{PLUS} Cài{S}{X} Tải lại{S}{L}/{R} Mục{S}{ZR} Tìm{S}{MINUS} Kiểu xem{S}{B} Huỷ',
    'inst.remote.search_hint': 'Tìm trong tất cả',
    'inst.remote.update_prompt_title': 'Cài kèm bản cập nhật?',
    'inst.remote.update_prompt_desc': 'Cài kèm bản cập nhật cho các mục đã chọn? Số bản cập nhật tìm thấy: ',
    'inst.remote.dlc_prompt_title': 'Cài kèm DLC?',
    'inst.remote.dlc_prompt_desc': 'Cài kèm DLC cho các mục đã chọn? Số DLC tìm thấy: ',
    'inst.remote.recheck': 'Kiểm tra lại',
    'inst.remote.back_home': 'Về màn hình chính',
    'inst.remote.motd_title': 'Thông báo từ kho',
    'inst.remote.buttons_installed': f'{A} Chi tiết{S}{X} Tải lại{S}{L}/{R} Mục{S}{B} Huỷ',
    'inst.remote.detail_type': 'Loại: ',
    'inst.remote.detail_titleid': 'Title ID: ',
    'inst.remote.detail_version': 'Phiên bản: ',
    'inst.remote.save_sync.title': 'Đồng bộ save',
    'inst.remote.save_sync.status.uploading_backup': 'Đang tải bản sao lưu save lên...',
    'inst.remote.save_sync.status.downloading_backup': 'Đang tải bản sao lưu save về...',
    'inst.remote.save_sync.status.packaging': 'Đang đóng gói dữ liệu save...',
    'inst.remote.save_sync.status.uploading_archive': 'Đang tải gói save lên...',
    'inst.remote.save_sync.status.preparing_restore': 'Đang chuẩn bị khôi phục save...',
    'inst.remote.save_sync.progress.preparing_upload': 'Đang chuẩn bị tải lên...',
    'inst.remote.save_sync.progress.preparing_download': 'Đang chuẩn bị tải về...',
    'inst.remote.save_sync.progress.scanning_files': 'Đang quét file...',
    'inst.remote.save_sync.progress.compressing_fmt': 'Đang nén ',
    'inst.remote.save_sync.progress.download_fmt': 'Tải về: ',
    'inst.remote.save_sync.progress.download_bytes_fmt': 'Tải về: ',
    'inst.remote.save_sync.progress.upload_archive_fmt': 'Đang tải gói lên: ',
    'inst.remote.save_sync.progress.upload_archive_bytes_fmt': 'Đang tải gói lên: ',
    'inst.remote.save_sync.progress.upload_wait_response_fmt': 'Đã tải lên xong, đang chờ máy chủ phản hồi... ',
    'inst.remote.save_sync.progress.upload_complete': 'Đã tải lên xong.',
    'inst.remote.save_sync.progress.starting_download': 'Bắt đầu tải save về...',
    'inst.remote.save_sync.progress.clearing_existing_files': 'Đang xoá file save cũ...',
    'inst.remote.save_sync.progress.applying_save_data': 'Đang ghi dữ liệu save...',
    'inst.remote.save_sync.progress.restoring_fmt': 'Đang khôi phục ',
    'inst.remote.save_sync.progress.complete': 'Đồng bộ save xong.',
    'inst.remote.save_sync.dialog.unable_resolve_entry': 'Không xác định được mục save.',
    'inst.remote.save_sync.dialog.replace_local_prompt': 'Thay save trên máy bằng bản trên máy chủ?',
    'inst.remote.save_sync.dialog.delete_selected_fmt': 'Xoá bản sao lưu đã chọn trên máy chủ?',
    'inst.remote.save_sync.dialog.delete_fmt': 'Xoá bản sao lưu trên máy chủ?',
    'inst.remote.save_sync.dialog.none_available': 'Không có save trên máy hay trên máy chủ cho game này.',
    'inst.remote.save_sync.dialog.success_generic': 'Đồng bộ save thành công.',
    'inst.remote.save_sync.dialog.uploaded_success': 'Đã tải save lên thành công.',
    'inst.remote.save_sync.dialog.downloaded_success': 'Đã tải save về thành công.',
    'inst.remote.save_sync.dialog.deleted_success': 'Đã xoá bản sao lưu save.',

    'inst.sd.help.title': 'Hướng dẫn',
    'inst.sd.help.desc': 'Chép file NSP, NSZ, XCI hoặc XCZ vào thẻ nhớ SD, tìm tới và chọn\ncác file cần cài, rồi bấm nút +.',
    'inst.sd.top_info': 'Chọn các file cần cài, rồi bấm nút +!',
    'inst.sd.source_string': ' từ thẻ nhớ SD',
    'inst.sd.delete_info': ' đã cài xong! Xoá file gốc khỏi thẻ nhớ SD?',
    'inst.sd.delete_info_multi': ' file đã cài thành công! Xoá các file gốc khỏi thẻ nhớ SD?',
    'inst.sd.delete_desc': 'Sau khi cài xong thì không cần giữ file gốc nữa',
    'inst.sd.buttons': f'{A} Chọn file{S}{Y} Chọn tất cả{S}{PLUS} Cài file{S}{X} Hướng dẫn{S}{B} Huỷ',

    'inst.hdd.help.title': 'Hướng dẫn',
    'inst.hdd.help.desc': 'Cắm ổ USB SSD hoặc ổ cứng, tìm tới và chọn file NSP, NSZ, XCI\nhoặc XCZ, rồi bấm nút +.',
    'inst.hdd.top_info': 'Chọn các file cần cài, rồi bấm nút +!',
    'inst.hdd.source_string': ' từ ổ USB',
    'inst.hdd.delete_info': ' đã cài xong! Xoá file gốc khỏi ổ USB?',
    'inst.hdd.delete_info_multi': ' file đã cài thành công! Xoá các file gốc khỏi ổ USB?',
    'inst.hdd.delete_desc': 'Sau khi cài xong thì không cần giữ file gốc nữa',
    'inst.hdd.show_hidden': 'Hiện file ẩn',
    'inst.hdd.hide_hidden': 'Giấu file ẩn',
    'inst.hdd.buttons': f'{A} Chọn file{S}{Y} Chọn tất cả{S}{PLUS} Cài file{S}{X} Hướng dẫn{S}{B} Huỷ',

    'inst.usb.help.title': 'Hướng dẫn',
    'inst.usb.help.desc': 'Có thể cài file qua USB từ máy khác bằng công cụ như NS-USBloader\n(chế độ Tinfoil). Mở phần mềm đó trên máy tính, chọn file rồi gửi sang máy!\n\nCài qua USB cần cấu hình riêng trên một số hệ điều hành và đôi khi hay lỗi.\nNếu không làm được, thử cài qua LAN/Internet, hoặc chép file vào thẻ nhớ SD\nrồi chọn "Cài từ thẻ nhớ SD" ở menu chính!',
    'inst.usb.top_info': 'Đã kết nối USB! Đang chờ máy tính gửi danh sách file...',
    'inst.usb.top_info2': 'Chọn các file cần cài qua USB, rồi bấm nút +!',
    'inst.usb.error': 'Truyền qua USB bị quá thời gian hoặc thất bại',
    'inst.usb.source_string': ' qua USB',
    'inst.usb.buttons': f'{X} (Giữ) Hướng dẫn{S}{B} (Giữ) Huỷ',
    'inst.usb.buttons2': f'{A} Chọn file{S}{Y} Chọn tất cả{S}{PLUS} Cài file{S}{B} Huỷ',

    'inst.target.desc0': 'Cài ',
    'inst.target.desc1': ' vào đâu?',
    'inst.target.desc00': 'Cài ',
    'inst.target.desc01': ' file đã chọn vào đâu?',
    'inst.target.opt0': 'Thẻ nhớ SD',
    'inst.target.opt1': 'Bộ nhớ máy',

    'inst.mtp.target.title': 'Cài qua MTP',
    'inst.mtp.target.desc': 'File cài qua MTP sẽ lưu vào đâu?',
    'inst.mtp.waiting.title': 'Cài qua MTP',
    'inst.mtp.waiting.desc': 'Cắm máy tính và chép file vào thư mục MTP Install.',
    'inst.mtp.waiting.hint': 'Bấm B để dừng MTP.',
    'inst.mtp.error.title': 'Lỗi MTP',
    'inst.mtp.error.desc': 'Không khởi động được MTP.',

    'inst.info_page.top_info0': 'Đang cài ',
    'inst.info_page.preparing': 'Đang chuẩn bị cài đặt...',
    'inst.info_page.failed': 'Cài thất bại: ',
    'inst.info_page.failed_desc': 'Phần đã cài dở có thể gỡ trong Cài đặt hệ thống (System Settings).',
    'inst.info_page.complete': 'Cài đặt xong',
    'inst.info_page.desc0': ' file đã cài thành công!',
    'inst.info_page.desc1': ' đã cài xong!',
    'inst.info_page.downloading': 'Đang tải ',
    'inst.info_page.at': ' tốc độ ',

    'inst.nca_verify.title': 'Phát hiện chữ ký NCA không hợp lệ!',
    'inst.nca_verify.desc': f'Chỉ nên cài phần mềm ký sai từ nguồn đáng tin cậy. Các bản Việt hóa\ntích hợp, bản chuyển từ băng và DLC unlocker luôn hiện cảnh báo này.\nCó thể tắt kiểm tra trong cài đặt {APP}.\n\nBạn có chắc muốn tiếp tục cài?',
    'inst.nca_verify.opt1': 'Có, tôi hiểu rủi ro',
    'inst.nca_verify.error': 'NCA này không được ký đúng: ',
    'inst.finished': [
        'Chúc bạn chơi game vui vẻ!',
        'Cảm ơn bạn đã ủng hộ SUPER MẠNH!',
        'Bản Việt hóa đã sẵn sàng, vào game thôi!',
        'Có lỗi chữ hay bản dịch? Báo cho SUPER MẠNH để sửa nhé!',
        'Theo dõi supermanh.com để nhận bản Việt hóa mới!',
    ],

    'sig.install': 'Cài',
    'sig.uninstall': 'Gỡ',
    'sig.update': 'Cập nhật',
    'sig.version_text': 'Signature patches hiện đã cài, hỗ trợ tới phiên bản HOS ',
    'sig.title0': 'Cài signature patches?',
    'sig.desc0': 'Cần signature patches để cài và chơi phần mềm.',
    'sig.backup_failed': 'Không sao lưu được patches.ini của Hekate! Vẫn cài?',
    'sig.backup_failed_desc': 'Nếu không dùng Hekate thì bỏ qua cảnh báo này.',
    'sig.download_failed': 'Không tải được signature patches',
    'sig.download_failed_desc': f'Có thể nguồn tải trong cài đặt {APP} bị sai,\nhoặc máy chủ đang tạm ngưng.',
    'sig.version_text2': 'Signature patches đã cập nhật, hỗ trợ tới phiên bản HOS ',
    'sig.install_complete': 'Cài xong!',
    'sig.complete_desc': 'Khởi động lại máy để áp dụng',
    'sig.restart': 'Khởi động lại',
    'sig.later': 'Để sau',
    'sig.extract_failed': 'Không giải nén được file!',
    'sig.restore_failed': 'Không khôi phục được patches.ini gốc của Hekate! Tiếp tục gỡ?',
    'sig.uninstall_complete': 'Gỡ xong',
    'sig.remove_failed': 'Không gỡ được signature patches',
    'sig.remove_failed_desc': 'File có thể đã bị đổi tên hoặc xoá',
    'sig.generic_error': 'Cài signature patches thất bại!',

    'options.theme_loading': 'Đang áp dụng giao diện…',
    'options.theme_picker': 'Chọn giao diện',
    'options.theme_picker_hint': f'{A} Chọn giao diện{S}{B} Quay lại',
    'options.theme_default': 'Mặc định',
    'options.menu_items.theme': 'Giao diện: ',
    'options.menu_items.usb_hdd_hidden': 'Hiện file ẩn trên ổ USB',
    'options.menu_items.ignore_firm': 'Bỏ qua firmware tối thiểu mà game yêu cầu',
    'options.menu_items.nca_verify': 'Kiểm tra chữ ký NCA trước khi cài',
    'options.menu_items.boost_mode': 'Bật "boost mode" khi cài đặt',
    'options.menu_items.ask_delete': 'Hỏi xoá file gốc sau khi cài',
    'options.menu_items.auto_update': f'Tự kiểm tra bản cập nhật {APP}',
    'options.menu_items.gay_option': 'Ẩn hình anime',
    'options.menu_items.sig_url': 'Nguồn tải signature patches: ',
    'options.menu_items.remote_url': 'Địa chỉ kho: ',
    'options.menu_items.remote_user': 'Tên đăng nhập kho: ',
    'options.menu_items.remote_pass': 'Mật khẩu kho: ',
    'options.menu_items.remote_hide_installed': 'Ẩn các bản đã cài trong kho',
    'options.menu_items.remote_hide_installed_section': 'Ẩn mục "Đã cài" trong kho',
    'options.menu_items.remote_all_base_only': 'Mục Tất cả chỉ hiện bản game gốc',
    'options.menu_items.remote_start_grid_mode': 'Mở kho ở dạng lưới',
    'options.menu_items.remote_reset_icons': 'Xoá bộ nhớ đệm icon của kho',
    'options.menu_items.language': 'Ngôn ngữ: ',
    'options.menu_items.check_update': f'Kiểm tra bản cập nhật {APP}',
    'options.menu_items.credits': 'Ghi công',
    'options.menu_items.sound': 'Bật âm thanh',
    'options.menu_items.oled': 'Bật chế độ OLED (nền đen)',
    'options.menu_items.mtp_album': 'Hiện ổ Album khi cài qua MTP',
    'options.nca_warn.title': 'Lưu ý!',
    'options.nca_warn.desc': 'Một số file cài đặt có thể chứa nội dung độc hại! Chỉ tắt tính năng này\nkhi bạn hoàn toàn chắc chắn phần mềm sắp cài là đáng tin cậy!\n\nVẫn muốn tắt kiểm tra chữ ký NCA?',
    'options.nca_warn.opt1': 'Có, tôi chịu rủi ro',
    'options.sig_hint': 'Nhập địa chỉ tải signature patches',
    'options.remote.url_hint': 'Nhập địa chỉ kho (ví dụ: https://supermanh.com/tinfoil)',
    'options.remote.user_hint': 'Nhập tên đăng nhập kho (không bắt buộc)',
    'options.remote.pass_hint': 'Nhập mật khẩu kho (không bắt buộc)',
    'options.cache_reset.title': 'Xoá bộ nhớ đệm icon?',
    'options.cache_reset.desc': 'Toàn bộ icon kho đã lưu sẽ bị xoá và tải lại ở lần mở kho tiếp theo.',
    'options.cache_reset.confirm': 'Xoá',
    'options.update.title': 'Có bản cập nhật',
    'options.update.desc0': f'{APP} ',
    'options.update.desc1': ' đã có! Cập nhật ngay?',
    'options.update.opt0': 'Cập nhật',
    'options.update.top_info': f'Đang cập nhật lên {APP} ',
    'options.update.bot_info': f'Đang tải {APP} ',
    'options.update.bot_info2': f'Đang giải nén {APP} ',
    'options.update.complete': 'Cập nhật xong!',
    'options.update.failed': 'Cập nhật thất bại!',
    'options.update.end_desc': 'Ứng dụng sẽ đóng lại bây giờ.',
    'options.update.title_check_fail': 'Không có bản cập nhật',
    'options.update.desc_check_fail': f'Bạn đang dùng bản {APP} mới nhất!',
    'options.credits.title': 'Cảm ơn những người sau!',
    'options.credits.desc': f'- luketanti với CyberFoil (ứng dụng này dựa trên CyberFoil)\n- Huntereb với Awoo Installer\n- ITotalJustice với Sphaira\n- XorTroll với Plutonium\n- Atmosphere Team với libhaze\n- DarkMatterCore với libusbhsfs\n- Cộng đồng Homebrew vì mọi công sức và tâm huyết\n- Và bạn, vì đã dùng {APP}!',
    'options.language.title': f'Chọn ngôn ngữ cho {APP}',
    'options.language.desc': 'Ứng dụng sẽ đóng sau khi đổi ngôn ngữ. Bấm B để huỷ.',
    'options.language.system_language': 'Theo hệ thống',
    'options.title': f'Cài đặt {APP}',
    'options.buttons': f'{A} Chọn/Đổi{S}{B} Huỷ',

    'common.ok': 'OK',
    'common.cancel': 'Huỷ',
    'common.close': 'Đóng',
    'common.yes': 'Có',
    'common.no': 'Không',
    'common.cancel_desc': 'Bấm B để huỷ',
}


def flatten(node, prefix=''):
    if isinstance(node, dict):
        for k, v in node.items():
            yield from flatten(v, f'{prefix}.{k}' if prefix else k)
    else:
        yield prefix, node


def build(node, prefix=''):
    if isinstance(node, dict):
        return {k: build(v, f'{prefix}.{k}' if prefix else k) for k, v in node.items()}
    if prefix not in VI:
        raise SystemExit(f'missing Vietnamese text for {prefix}')
    return VI[prefix]


en = json.loads((ROOT / 'romfs/lang/en.json').read_text(encoding='utf-8'))
extra = set(VI) - {k for k, _ in flatten(en)}
if extra:
    raise SystemExit(f'unknown keys: {sorted(extra)}')
import re
out = build(en)
for key, en_text in flatten(en):
    vi_text = VI[key]
    if isinstance(en_text, str) and re.findall('[\ue000-\uf8ff]', en_text) != re.findall('[\ue000-\uf8ff]', vi_text):
        raise SystemExit(f'button glyphs differ from en.json in {key}')
(ROOT / 'romfs/lang/vi.json').write_text(json.dumps(out, ensure_ascii=False, indent=4) + '\n', encoding='utf-8')
print('vi.json:', len(VI), 'strings')
