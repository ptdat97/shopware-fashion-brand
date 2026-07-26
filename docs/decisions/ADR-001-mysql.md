# ADR-001: Phiên bản MySQL

**Trạng thái:** ✅ **ĐÃ GIẢI QUYẾT** — 26/07/2026
**Quyết định:** Phương án A — chuyển sang MySQL 8.0.33

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
