# Nguyên tắc kỹ thuật

Nguồn quyết định khi có mâu thuẫn vẫn là [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md). Bản rút gọn thành quy tắc có mã: [13-chuan-phat-trien-plugin.md](13-chuan-phat-trien-plugin.md).

---

## 1. Phạm vi thay đổi

Nguyên tắc quan trọng nhất, áp dụng nguyên vẹn cho mọi loại code.

### Khi sửa lỗi

1. **Coi gợi ý trong issue là giả thuyết**, không phải kết luận. Người báo lỗi mô tả triệu chứng, không phải nguyên nhân.
2. **Grep các nơi gọi tới** trước khi sửa hành vi dùng chung — một thay đổi tưởng nhỏ có thể phá nơi khác.
3. **Sửa đúng ranh giới nơi lỗi thực sự nằm**, không vá ở chỗ triệu chứng lộ ra.
4. Lỗi ở tầng framework giữ trong framework; lỗi nghiệp vụ cụ thể **không nhét vào** code dùng chung.

### Khi dọn dẹp tiện thể

- Áp dụng cùng một cách dọn trong file đang sửa **nếu an toàn và hiển nhiên**.
- **Nêu ra** cơ hội dọn dẹp rộng hơn thay vì âm thầm mở rộng phạm vi.
- Khi động vào test, bổ sung coverage còn thiếu nếu rẻ.
- **Không** biến một bản sửa lỗi tập trung thành refactor không liên quan.

---

## 2. Kiến trúc code PHP

### Ưu tiên điểm mở rộng có sẵn

> *"Prefer the existing Shopware extension point over a new abstraction."*

Trước khi tạo interface/abstraction mới, kiểm tra Shopware đã có cơ chế diễn đạt đúng ý đồ chưa:

- **Twig inheritance** và **Twig blocks** — tuỳ biến giao diện
- **DAL entities** — mở rộng dữ liệu
- **Admin API routes** — tích hợp bên ngoài
- **Event subscriber / decorator** — chen vào luồng xử lý

Đây chính là nền tảng kỹ thuật của quy tắc không sửa core trong [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md).

### Tách tầng (hexagonal)

- Controller, CLI command, subscriber, handler làm nhiệm vụ **dịch chi tiết hạ tầng** (`Request`, IO, database, filesystem, HTTP) thành input thuần trước khi gọi service.
- **Service không được** thao tác hạ tầng trực tiếp hay phụ thuộc object của framework. Phụ thuộc vào abstraction hẹp: repository, filesystem interface, HTTP client, gateway.
- Service phải **unit-test được mà không cần hệ thống ngoài**. Adapter hạ tầng thì test bằng integration test.

### Đánh dấu phạm vi

- Adapter hạ tầng mặc định đánh dấu `@internal`.
- Class public không có ý định cho kế thừa thì đánh dấu `@final`.
- Value object / struct đơn giản không cần mở rộng, decorate, mock → dùng `final class` thật.
- **Không** thêm `@final` vào class đã có `@internal` — marker internal là đủ.
- **Không** lặp lại `@internal` trên constructor/method bên trong class đã `@internal`.

### Tiết chế DTO

Chỉ thêm DTO/value object khi nó **diễn đạt một khái niệm nghiệp vụ có nghĩa**, vượt qua một ranh giới thật, hoặc làm gọn một contract public.

Dữ liệu nội bộ đơn giản → dùng scalar hoặc array. **Không** tạo DTO chỉ để mô tả việc truyền dữ liệu riêng tư bên trong một class.

Với struct trong suốt, ưu tiên **public readonly property** thay vì private property kèm getter tầm thường.

---

## 3. Migration

- Dùng **Unix timestamp thật tại thời điểm tạo** cho tên class, tên file, và giá trị `getCreationTimestamp()`. Không dùng số làm tròn hay placeholder.
- **Không** viết test cho `updateDestructive()` rỗng/no-op. Chỉ test hành vi migration thực sự thay đổi trạng thái.

---

## 4. Viết test

> *"Tests should read like executable examples."*

### Hình dạng test

- Test method là **ví dụ chạy được** của hành vi đang kiểm tra: setup theo kịch bản → hành động → assert, dễ đọc ngay trong thân test.
- Ưu tiên **setup kịch bản tường minh** hơn là giấu logic trong factory. Helper tạo entity/file/value object; thân test làm phần wiring có ý nghĩa giải thích hành vi.
- Boilerplate ổn định (mock service, class under test, command tester, thư mục tạm) đưa vào `setUp()` / `tearDown()` để test cụ thể chỉ còn dữ liệu và thao tác riêng của kịch bản.
- **Helper phải nhỏ hơn code nó thay thế.**
- **Không** giấu assertion sau abstraction khi assert trực tiếp cũng dễ đọc như vậy.
- Ưu tiên **một test tập trung cho mỗi exception/hành vi riêng biệt** hơn là gộp vào data provider lớn khi mỗi case mang ý nghĩa khác nhau.

### Assertion và fixture

- Ưu tiên `expectExceptionObject()` hơn `expectException()` chung chung. Dựng exception kỳ vọng qua **cùng domain factory** mà production dùng, để class/code/message luôn khớp.
- **Không** mock hành vi của DBAL `Connection` trong unit test bằng cách assert lời gọi SQL. Stub collaborator dùng DBAL; cô lập adapter SQL và phủ chúng bằng integration test.

### Data provider

- Dùng **`yield` có đặt tên** thay vì return array, kể cả provider nhỏ.
- **Không** dùng `yield from` với array inline. Mỗi kịch bản một dòng `yield 'mô tả dễ hiểu' => [...]`.
- Tên case phải **giải thích quy tắc đang chứng minh** (thứ tự ưu tiên, chuẩn hoá, đổi múi giờ, xử lý biên), không phải đọc lại giá trị input.
- Thận trọng khi xoá case "trùng lặp" — chỉ xoá case trùng nghĩa hoàn toàn, giữ lại case trông giống nhau nhưng phủ biên khác nhau.

---

## 5. Code Administration (Vue/JS)

Áp dụng nếu plugin có module admin riêng.

- Dùng **TypeScript** cho code mới.
- Theo đúng pattern component / module / service / repository / store đang có.
- UI admin có đọc hoặc ghi **DAL entity hay association** → cập nhật **ACL privilege** tương ứng, và migration cho các role đã tồn tại nếu cần.
- Viết **Jest test** cho tính năng mới và bản sửa lỗi.
- Đặt test **cạnh code** với đuôi `.spec.ts`.
- Spec quá lớn → tách thành thư mục `.spec/` chia theo nhóm hành vi.

---

## 6. Lưu kiến thức vào đâu

Nguyên tắc: **đặt kiến thức vào nơi nhỏ nhất mà người đọc sau này tự nhiên sẽ tìm tới.**

1. Xác định kiến thức là gì, **ai cần nó về sau**, và phạm vi (cục bộ / toàn dự án / theo lĩnh vực).
2. **Tái sử dụng file có sẵn** trước khi tạo file mới.
3. Chỉ lưu kiến thức **bền vững**. Ngữ cảnh nhất thời để lại trong cuộc trò chuyện.
4. **Xoá hoặc cập nhật hướng dẫn cũ mâu thuẫn** ngay trong cùng lần sửa.
5. Liên kết chéo tiết kiệm — chỉ link khi nó **thay đổi việc người đọc cần làm**.

Bản đồ cho dự án này:

| Loại kiến thức | Nơi lưu |
|---|---|
| Quy tắc code, quy ước chung | [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md) |
| Nguyên tắc kỹ thuật chi tiết | Tài liệu này |
| Quyết định có đánh đổi, ảnh hưởng lâu dài | [decisions/](decisions/) |
| Quy trình thao tác lặp lại | [runbook/](runbook/) |
| Điều hướng tổng thể | [README.md](README.md) |

---

## 7. Ngoài phạm vi

Các quy tắc sau thuộc về việc **đóng góp code vào lõi `shopware/shopware`**, không áp dụng cho dự án này:

- OpenAPI schema cho route Admin/Store API của lõi
- Cơ chế deprecation nội bộ: `Feature::triggerDeprecationOrThrow()`, `Feature::silent()`
- Attribute `#[Package(...)]` trên test — dùng định tuyến CI theo team của Shopware
- Tài liệu phát hành `RELEASE_INFO-6.x.md`, `UPGRADE-6.x.md`

Dự án dùng repo riêng nên cũng không theo template PR hay quy ước commit của shopware/shopware.
