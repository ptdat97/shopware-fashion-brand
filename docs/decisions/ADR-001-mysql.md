# ADR-001: Phiên bản MySQL

**Trạng thái:** ✅ **ĐÃ GIẢI QUYẾT** — 26/07/2026 · **cập nhật 26/10/2026: chuyển lên MySQL 8.4 LTS** (mục cuối)
**Quyết định:** ~~Phương án A — chuyển sang MySQL 8.0.33~~ → **MySQL 8.4 LTS** (local: 8.4.7 trên DBngin)

## Bối cảnh ban đầu

Hệ thống chạy MySQL **9.7.1**, nằm ngoài ma trận hỗ trợ chính thức của Shopware 6.7 (thường là MySQL 8.0/8.4 hoặc MariaDB 10.11+).

Nghiêm trọng hơn: `mysqldump` trên máy là bản **8.0.33**, cũ hơn server một major version. Client cũ hơn server có thể xuất backup thiếu hoặc sai — rủi ro chỉ bộc lộ vào đúng lúc cần khôi phục.

## Quyết định

Chuyển database sang **MySQL 8.0.33**.

Lý do chọn thời điểm này: khi đó `order = 0` và catalog chỉ có demo data, nên chi phí migrate gần như bằng không. Sau khi có đơn hàng thật, đổi phiên bản database sẽ thành thao tác rủi ro cao cần downtime.

## Đã thực hiện

1. Backup database từ 9.7.1 (`--single-transaction --routines --triggers --events --set-gtid-purged=OFF`)
2. Kiểm chứng dump: **260 `CREATE TABLE`**, khớp đúng 260 bảng của nguồn
3. Rà soát dump không chứa cú pháp version-gated `/*!9xxxx` — an toàn để nạp vào 8.0
4. Cài đặt MySQL 8.0.33 và tạo lại database `shopware`
5. Khôi phục dữ liệu
6. `cache:clear` + `dal:refresh:index`
7. Kiểm chứng toàn bộ (xem bên dưới)

## Kết quả kiểm chứng

| Hạng mục | Trước (9.7.1) | Sau (8.0.33) |
|---|---|---|
| Số bảng | 260 | 260 ✅ |
| product | 16 | 16 ✅ |
| category | 9 | 9 ✅ |
| customer | 1 | 1 ✅ |
| order | 0 | 0 ✅ |
| language | 31 | 31 ✅ |
| snippet_set | 31 | 31 ✅ |

Cấu hình tiếng Việt còn nguyên vẹn:

```
http://shopware.test    vi-VN   LanguagePack vi-VN
https://shopware.test   vi-VN   LanguagePack vi-VN
default.headless0       en-US   BASE en-GB
swag_language_pack_language: vi-VN  sales_channel_active=1  administration_active=1
```

Ứng dụng chạy đúng:

```
storefront: 200      admin: 200
<html lang="vi-VN">
Chuỗi tiếng Việt render thật: Giỏ hàng, Tài khoản, Tìm kiếm, Đăng nhập
```

## Vấn đề backup đã được giải quyết

**Trước:**
```
mysqldump  Ver 8.0.33     ← cũ hơn server
server     9.7.1
stderr: Warning: A partial dump from a server that has GTIDs...
```

**Sau:**
```
mysqldump  Ver 8.0.33
server     8.0.33          ← khớp
stderr: (rỗng)
```

### Đã test khôi phục thật

Không chỉ tạo backup mà đã **khôi phục thử vào database trống** và đối chiếu:

| Bảng | Nguồn | Khôi phục |
|---|---|---|
| tables | 260 | 260 ✅ |
| product | 16 | 16 ✅ |
| language | 31 | 31 ✅ |
| snippet_set | 31 | 31 ✅ |

Database test đã được xóa sau khi kiểm chứng xong.

## Ghi chú kỹ thuật

Trong quá trình kiểm chứng, `grep -c "CREATE TABLE"` trả về rỗng trên file dump, ban đầu trông như dump hỏng. Nguyên nhân thực tế là **grep không xử lý được dòng dài 31.242 ký tự** trong file, không phải lỗi dump. Đếm lại bằng PHP đọc từng dòng cho kết quả đúng 260.

> **Bài học:** khi kiểm chứng file SQL dump lớn, không tin `grep` — dump của Shopware có những dòng `INSERT` rất dài. Dùng script đọc theo dòng.

## Rủi ro còn lại

- **Không còn** rủi ro về phiên bản không được hỗ trợ
- **Không còn** rủi ro backup không đáng tin
- Vẫn cần thiết lập **backup tự động, lưu khác máy chủ** cho môi trường production — xem [../runbook/00-backup.md](../runbook/00-backup.md)

## Cập nhật 26/10/2026: MySQL 8.4 LTS

**Vì sao đổi:** MySQL 8.0 hết hỗ trợ (cả extended support) ngày **30/04/2026** — không còn bản vá bảo mật, không còn phù hợp cho production. Cùng lúc phát hiện DB local đang chạy MySQL **9.7.2** (không rõ từ khi nào, có thể khi chuyển máy chủ DBngin) — dòng 9.x không có trong tài liệu và danh sách đã kiểm thử của Shopware 6.7 ("tested on MySQL 8"), và `mysqldump` mặc định trên máy (8.0.33) lệch major với server — đúng rủi ro ở mục Bối cảnh.

**Vì sao 8.4:** Shopware 6.7 yêu cầu MySQL ≥ 8.0.17 (tránh 8.0.20, 8.0.21) hoặc MariaDB ≥ 10.11; 8.4 thuộc dòng 8.x được kiểm thử, cộng đồng xác nhận hỗ trợ từ 6.6.3. 8.4 là bản **LTS**: bản vá tới 04/2032, nhà cung cấp hosting phổ biến.

**Đã làm (26/10/2026):**

1. Backup bằng `mysqldump` **9.7.2** (cùng major với server): 258/258 bảng; rà dump không có cú pháp version-gated `/*!9xxxx`, mọi bảng `utf8mb4_unicode_ci` + InnoDB
2. Chủ dự án cài MySQL 8.4.7 trên DBngin (cổng 3306); nạp dump bằng client 8.4.7 → 258 bảng, 79 đơn, 77 sản phẩm, 10 plugin bật
3. `group_concat_max_len`, `sql_mode` để mặc định — Shopware tự đặt cho mỗi kết nối (`MySQLFactory`: ≥ 320000, bỏ `ONLY_FULL_GROUP_BY`)
4. `cache:clear`, `cache:clear:http`, `dal:refresh:index`
5. Kiểm: test 7 plugin đạt (133, DB test `shopware_test` dựng lại trên 8.4), e2e Playwright 12 đạt (đặt đơn, email, khách vãng lai)
6. Backup đầu tiên trên 8.4 bằng `mysqldump` 8.4.7: 258 bảng

**Còn lại:** bản 9.7.2 (DBngin) giữ nguyên dữ liệu để lùi khi cần — xoá sau khi chạy ổn một thời gian. Production: MySQL 8.4 LTS (hoặc MariaDB ≥ 10.11 nếu nhà cung cấp chỉ có MariaDB — kiểm lại toàn bộ test trước).

