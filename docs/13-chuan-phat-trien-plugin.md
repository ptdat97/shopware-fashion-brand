# Chuẩn phát triển plugin

Bộ quy tắc **bắt buộc** cho mọi plugin trong `custom/plugins/`, theo cách chính Shopware viết core (đối chiếu trong `vendor/shopware/` bản 6.7.15.1) và theo yêu cầu kiến trúc doanh nghiệp: ranh giới rõ, thay được từng phần, nâng cấp Shopware không vỡ, kiểm chứng được.

Tài liệu này là **luật chi tiết**. Năm quy tắc nền tảng nằm ở [08-quy-tac-phat-trien.md](08-quy-tac-phat-trien.md); cách viết code PHP/test ở [09-nguyen-tac-ky-thuat.md](09-nguyen-tac-ky-thuat.md); quy trình tạo plugin ở [runbook/06-tao-plugin.md](runbook/06-tao-plugin.md).

## Cách dùng

- Mỗi quy tắc có **mã** (`DATA-04`) — dùng để trích khi review: *"vi phạm DATA-04"*.
- **BẮT BUỘC**: vi phạm thì không merge. **NÊN**: làm khác phải ghi lý do trong docblock hoặc PR.
- **Ngoại lệ** cho một quy tắc BẮT BUỘC chỉ hợp lệ khi có ADR trong [decisions/](decisions/) — ví dụ JS Administration viết tay không qua build (xem ADM-04).
- Cột *Nguồn* chỉ nơi kiểm chứng quy tắc: file trong `vendor/` (cách core làm) hoặc sự cố đã gặp trong dự án.

---

## 1. Kiến trúc & ranh giới — `ARCH`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| ARCH-01 | BẮT BUỘC | Mỗi plugin sở hữu **đúng một mảng nghiệp vụ**; tắt plugin chỉ mất chức năng của chính nó | [08 · Quy tắc 5](08-quy-tac-phat-trien.md) |
| ARCH-02 | BẮT BUỘC | **Không** `use` / inject class, service, constant của plugin khác. Kiểm: `grep -rn "use Acme" custom/plugins/AcmeX/src` chỉ ra namespace của AcmeX | 08 · Quy tắc 5 |
| ARCH-03 | BẮT BUỘC | Phụ thuộc **dữ liệu** của plugin khác (custom field, bảng, tỉnh/thành) theo kiểu *có thì dùng, không có thì bỏ qua* — không lỗi, không 500 | `AcmeShipping` · `InnerCityStateSync` |
| ARCH-04 | BẮT BUỘC | Trước khi tạo mới, dùng theo thứ tự: trường sẵn có → cấu hình native → custom field → điểm mở rộng → bảng riêng (phải ghi lý do) | [08 · Quy tắc 4](08-quy-tac-phat-trien.md) |
| ARCH-05 | BẮT BUỘC | Phân biệt **bề mặt công khai** (entity DAL, route Store/Admin API, sự kiện, block Twig, template context) với **chi tiết nội bộ**. Liệt kê bề mặt công khai trong docblock class plugin | hướng dẫn PHP của Shopware |
| ARCH-06 | BẮT BUỘC | Không chèn logic plugin vào cart / checkout / payment khi Rule Builder, khuyến mãi, phương thức có sẵn làm được | [ADR-003](decisions/ADR-003-mo-hinh-thuong-hieu.md), MB5 |
| ARCH-07 | NÊN | Tách tầng (hexagonal): controller, command, subscriber, handler chỉ dịch hạ tầng (`Request`, IO, DB) thành input thuần rồi gọi service; service không đụng hạ tầng trực tiếp | [09 · mục 2](09-nguyen-tac-ky-thuat.md) |

## 2. Cấu trúc & đặt tên — `STR`

Cấu trúc chuẩn (chỉ tạo thư mục khi có nội dung):

```
custom/plugins/AcmeX/
├── composer.json
├── phpunit.xml                       ← khi có test (luôn nên có)
├── src/
│   ├── AcmeX.php                     ← class plugin: docblock nghiệp vụ, lifecycle
│   ├── XException.php                ← exception miền (DATA-07)
│   ├── Core/Content/<Miền>/          ← Definition, Entity, Collection, DataAbstractionLayer/ (indexer)
│   ├── Extension/                    ← EntityExtension
│   ├── Migration/
│   ├── Storefront/{Controller,Page}/
│   ├── Command/  Subscriber/  Validation/  Seo/  Sitemap/
│   └── Resources/
│       ├── config/{services.xml,routes.xml,config.xml}
│       ├── views/storefront/…        ← chỉ ghi đè block
│       ├── snippet/{vi_VN,en_GB}/storefront.*.json
│       ├── app/storefront/src/scss/base.scss
│       ├── app/administration/src/snippet/{vi-VN,en-GB}.json
│       └── public/administration/…   ← xem ADM-04
└── tests/{Unit,Integration}/  TestBootstrap.php
```

| Mã | Mức | Quy tắc |
|---|---|---|
| STR-01 | BẮT BUỘC | Tên plugin `Acme<Nghiệp vụ>`, namespace PHP `Acme\<Nghiệp vụ>\`, gói composer `acme/<nghiệp-vụ>`. Đổi namespace plugin đã cài: [ADR-004](decisions/ADR-004-namespace-acme-theme.md) |
| STR-02 | BẮT BUỘC | Mọi định danh kỹ thuật mang tiền tố của dự án để không đụng core/plugin khác: bảng & entity `acme_*`, custom field `acme_*`, cấu hình `AcmeX.config.*`, route `frontend.acme.*` / `api.action.acme_*`, khoá snippet `acme-<plugin>.*`, class CSS `.acme-*` |
| STR-03 | BẮT BUỘC | `composer.json` đủ: `name`, `type: shopware-platform-plugin`, `version` (semver), `require.shopware/core: ~6.7`, `autoload.psr-4`, `autoload-dev` (tests), `extra.shopware-plugin-class`, `extra.label` + `extra.description` cho `vi-VN` và `en-GB` |
| STR-04 | BẮT BUỘC | **Không** `composer require` plugin tự viết — [08 · Quy tắc 2](08-quy-tac-phat-trien.md) |
| STR-05 | BẮT BUỘC | `declare(strict_types=1)` mọi file PHP; kiểu tham số/trả về đầy đủ; thuộc tính `readonly` khi có thể |
| STR-06 | BẮT BUỘC | Docblock class plugin ghi: nghiệp vụ, bề mặt công khai, **tắt/gỡ thì điều gì xảy ra**, phụ thuộc dữ liệu (nếu có) — mẫu: `AcmeShipping.php`, `AcmeBrand.php` |
| STR-07 | NÊN | Đánh dấu `@internal` cho adapter (controller, subscriber, loader, migration, command); không thêm `@final` vào class đã `@internal`; value object đơn giản dùng `final class` với thuộc tính `public readonly` |

## 3. Vòng đời plugin — `LIFE`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| LIFE-01 | BẮT BUỘC | Cấu hình cần tái lập trên staging/production viết bằng **migration**, không SQL tay, không bấm Admin rồi quên | CLAUDE.md, bài học Phase 0 |
| LIFE-02 | BẮT BUỘC | `deactivate()` / `uninstall()` **không hoàn tác cấu hình kinh doanh** nằm trên entity core (tiền tệ, thuế, phí ship…) — hoàn tác làm sai đơn. Ghi rõ trong docblock | `AcmeVietnam`, `AcmeShipping` |
| LIFE-03 | BẮT BUỘC | `uninstall()` khi **không** `keepUserData()`: xoá bảng/định nghĩa custom field **của chính plugin**; không xoá dữ liệu của core (nhà sản xuất, sản phẩm, đơn) | `AcmeBrand::uninstall()` |
| LIFE-04 | BẮT BUỘC | Phương thức thanh toán/vận chuyển plugin tạo ra: `deactivate` thì tắt, `uninstall` thì **tắt chứ không xoá** — đơn cũ tham chiếu tới nó | `AcmePayment` |
| LIFE-05 | BẮT BUỘC | Dữ liệu cần ghi lại sau khi cập nhật (handler đổi, cấu hình mới) làm trong `update()`; dữ liệu cần đồng bộ lại theo môi trường làm trong `activate()` — idempotent | `AcmePayment::update()`, `AcmeShipping::activate()` |
| LIFE-06 | BẮT BUỘC | Theme plugin: đổi sales channel sang theme khác **trước** khi tắt — core chặn tắt theme đang gán | kiểm 14/10/2026 |

## 4. Dữ liệu & DAL — `DATA`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| DATA-01 | BẮT BUỘC | Ghi dữ liệu nghiệp vụ qua **repository DAL** (để có sự kiện, indexer, cache, validation). SQL thô chỉ trong migration, indexer, và luôn dùng **tham số**, không nối chuỗi | core DAL |
| DATA-02 | BẮT BUỘC | Mở rộng entity core bằng `EntityExtension` + bảng 1–1 của plugin; **không** thêm cột vào bảng core | `ProductManufacturerBrandExtension` |
| DATA-03 | BẮT BUỘC | Chọn nơi lưu: thuộc tính phụ, không ràng buộc, cần dịch → **custom field** của entity core; cần `UNIQUE`, khoá ngoại, index để lọc → **bảng riêng** (ghi lý do trong docblock Definition) | [ADR-003](decisions/ADR-003-mo-hinh-thuong-hieu.md) · rà soát 14/10 |
| DATA-04 | BẮT BUỘC | Khoá ngoại tới entity **có version** (sản phẩm, nhà sản xuất, CMS…) phải kèm `ReferenceVersionField` và cột `*_version_id`; ràng buộc DB khai báo `ON DELETE` có chủ đích (CASCADE / SET NULL / RESTRICT) | `BrandDefinition`, `SalesChannelTrackingOrderDefinition` của core |
| DATA-05 | BẮT BUỘC | Cờ `ApiAware` chỉ gắn cho trường **được phép** lộ ra Store API; association mặc định `autoload: false` — bật `true` phải ghi lý do | `ProductManufacturerBrandExtension` |
| DATA-06 | BẮT BUỘC | Validation nghiệp vụ ở **tầng ghi DAL** (`PreWriteValidationEvent`) để phủ mọi đường ghi (Admin, API, Sync, Import/Export) — không chỉ validate ở form Admin | `BrandCodeValidator`, `FeedLabelValidator` của core |
| DATA-07 | BẮT BUỘC | Lỗi miền là class kế thừa `Shopware\Core\Framework\HttpException` với **factory tĩnh** và mã lỗi `ACME_<PLUGIN>__<LÝ_DO>`; không `throw new \RuntimeException` cho lỗi nghiệp vụ | `BrandException`, `CategoryException` của core |
| DATA-08 | BẮT BUỘC | Tính duy nhất do **ràng buộc DB** đảm bảo (không SELECT-rồi-INSERT); dịch lỗi trùng khoá thành lỗi 400 bằng `ExceptionHandlerInterface` (tag `shopware.dal.exception_handler`) | `BrandUniqueExceptionHandler` |
| DATA-09 | BẮT BUỘC | Dữ liệu dẫn xuất (SEO URL, chỉ mục, giá trị tính sẵn) cập nhật qua **`EntityIndexer`** để `dal:refresh:index` phủ được — không tự viết subscriber + lệnh console riêng | `BrandIndexer` (theo `LandingPageIndexer` của core) |
| DATA-10 | BẮT BUỘC | Bảng `*_translation`: mọi UPDATE/INSERT lọc theo **cả** `language_id` | [runbook/06](runbook/06-tao-plugin.md), sự cố bảng size |

## 5. Migration — `MIG`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| MIG-01 | BẮT BUỘC | Tên class, tên file, `getCreationTimestamp()` dùng **Unix timestamp thật** lúc tạo (`date +%s`) | hướng dẫn PHP của Shopware |
| MIG-02 | BẮT BUỘC | **Idempotent**: chạy lại không sinh trùng, không lỗi — dò theo giá trị **không đổi** giữa các lần chạy (technical name, mã ISO), không theo tên đã dịch | [10 · bài học](10-phase2-catalog.md) |
| MIG-03 | BẮT BUỘC | **Không ghi đè dữ liệu người vận hành đã sửa**: chỉ ghi khi trống / còn giá trị mặc định; đổi template SEO chỉ khi còn đúng bản trước | `Migration1791972614LowercaseSeoUrls`, `…VietnamZoneShipping` |
| MIG-04 | BẮT BUỘC | **Không sửa migration đã chạy** ở bất kỳ môi trường nào. Đổi logic = migration mới | Shopware lưu migration đã chạy theo tên class |
| MIG-05 | BẮT BUỘC | Thay đổi phá huỷ (DROP bảng/cột) đặt trong `updateDestructive()`; chạy riêng `database:migrate-destructive <Plugin> --all` — `plugin:update` **không** chạy phần này | `MigrationCollection::migrateInPlace()` |
| MIG-06 | BẮT BUỘC | Chuyển logic sang plugin khác: migration **mới, idempotent** ở plugin đích + xoá file ở plugin nguồn; bản mới an toàn trên môi trường đã có bản cũ | đợt tách 14/10/2026 |
| MIG-07 | BẮT BUỘC | Thêm migration thì **tăng `version`** trong `composer.json` — không tăng thì `plugin:update` không chạy gì | `PluginLifecycleService::hasPluginUpdate()` |
| MIG-08 | NÊN | Test migration có thay đổi trạng thái: chạy lại vô hại, không ghi đè dữ liệu đã sửa. **Không** test `updateDestructive()` rỗng | [09 · mục 3](09-nguyen-tac-ky-thuat.md) |

## 6. API — `API`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| API-01 | BẮT BUỘC | Route Admin API: scope `ApiRouteScope` và **luôn** khai báo quyền `PlatformRequest::ATTRIBUTE_ACL => ['<entity>:<quyền>']` | `AppPrivilegeController` của core, `DemoDataController` |
| API-02 | BẮT BUỘC | Route Store API: lớp `Abstract<Tên>Route` có `getDecorated()` + bản cài đặt; scope `StoreApiRouteScope`; trả `StoreApiResponse` — để plugin khác **decorate** được | `AbstractLandingPageRoute` của core |
| API-03 | BẮT BUỘC | Controller mỏng: đọc request → gọi loader/service → trả response. Không truy vấn DB, không logic nghiệp vụ trong controller | `BrandController` |
| API-04 | BẮT BUỘC | Hợp đồng HTTP (đường dẫn, tham số, mã lỗi) là bề mặt công khai — ghi trong tài liệu của plugin, đổi phải có lộ trình tương thích, kể cả khi class controller là `@internal` | hướng dẫn PHP của Shopware |
| API-05 | NÊN | Tích hợp hệ thống ngoài (ERP, PIM) đi qua Admin API / Sync API bằng **Integration** có quyền tối thiểu, không mở route riêng nếu API chuẩn đã đủ | [12-da-thuong-hieu.md](12-da-thuong-hieu.md) |

## 7. Điểm mở rộng — `EXT`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| EXT-01 | BẮT BUỘC | Chen vào luồng core theo thứ tự ưu tiên: **Extension point** (`<tên>.pre/.post/.error`, vd. `product.listing.criteria`) → **sự kiện** → **decorator** của lớp `Abstract*` → (không bao giờ) sửa core | `Framework/Extensions/ExtensionDispatcher.php` |
| EXT-02 | BẮT BUỘC | Decorator gọi `getDecorated()` cho phần không đổi; không kế thừa class cài đặt của core | `Abstract*Route` của core |
| EXT-03 | BẮT BUỘC | Trang storefront của plugin **phát sự kiện** riêng (`<Trang>LoadedEvent extends PageLoadedEvent`) để plugin khác mở rộng mà không sửa code | `BrandPageLoadedEvent` |
| EXT-04 | BẮT BUỘC | Twig: `sw_extends` + ghi đè **block**, gọi `parent()` khi chỉ thêm nội dung; **không** chép nguyên file template core | [02-theme.md](02-theme.md) |
| EXT-05 | BẮT BUỘC | Không ghi đè cả block lớn của core chỉ để đổi một biến — tìm block nhỏ hơn, hoặc thêm sau `parent()` (vd. đặt lại `window.activeNavigationId` sau block router) | `AcmeBrand/…/meta.html.twig` |

## 8. Storefront — `SF`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| SF-01 | BẮT BUỘC | Trang mới theo mẫu core: `<Trang>Page extends Page` + `<Trang>PageLoader` dùng `GenericPageLoaderInterface` + sự kiện (EXT-03) | `LandingPageLoader` của core |
| SF-02 | BẮT BUỘC | Danh sách sản phẩm dùng **route listing của core** (phân trang, sắp xếp, bộ lọc native), không tự truy vấn và tự phân trang | ADR-003 · spike MB3 |
| SF-03 | BẮT BUỘC | Markup plugin nào thì **SCSS plugin đó**; plugin ngoài theme chỉ dùng biến Bootstrap; theme không định dạng hộ khối của plugin khác | 08 · Quy tắc 5 |
| SF-04 | BẮT BUỘC | Nhãn hiển thị trong file snippet (`snippet/vi_VN`, `snippet/en_GB`), không viết cứng tiếng Việt trong Twig/PHP | — |
| SF-05 | BẮT BUỘC | Trang có URL thân thiện: `SeoUrlRouteInterface` + template mặc định qua migration + canonical; có trong sitemap (`AbstractUrlProvider`) | `BrandPageSeoUrlRoute`, `BrandUrlProvider` |
| SF-06 | BẮT BUỘC | Hiểu HTTP cache: dữ liệu đọc qua DAL tự có cache tag; đổi template/code phải `cache:clear:http` (`cache:clear` không xoá HTTP cache) | sự cố MB3 |

## 9. Administration — `ADM`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| ADM-01 | BẮT BUỘC | Nhãn Admin đặt trong `Resources/app/administration/src/snippet/<locale>.json` (core đọc phía server, gộp sau gói ngôn ngữ) — **không** `Locale.extend` trong JS | `SnippetFinder` của core, rà soát 14/10 |
| ADM-02 | BẮT BUỘC | Thêm thuộc tính cho trang có sẵn bằng `Component.override` ghi đè **block**, không thay cả component; dùng thẻ custom field native khi dữ liệu là custom field | `acme-brand.js` |
| ADM-03 | BẮT BUỘC | Entity mới: khai báo quyền trong mapping ACL (gắn theo role của entity core liên quan) **và** migration cấp quyền cho role đã có (`addAdditionalPrivileges`) | `Migration1791823565AddBrandPrivileges` |
| ADM-04 | NGOẠI LỆ | JS Administration viết tay trong `public/administration/` + `.vite/entrypoints.json`, **không** có `main.js` — dự án chưa có `node_modules` để build. Thay Jest bằng thao tác thật + ảnh chụp | [runbook/05](runbook/05-demo-data.md) |

## 10. Hiệu năng & bất đồng bộ — `PERF`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| PERF-01 | BẮT BUỘC | Criteria khai báo **đúng** association cần dùng; không truy vấn trong vòng lặp (N+1); không tải lại dữ liệu trang đã có (vd. `page.product.seoCategory`) | rà soát `SizeChartSubscriber` |
| PERF-02 | BẮT BUỘC | Việc nặng hoặc chạy lâu (nhập hàng loạt, gọi API ngoài, sinh file) không chạy trong request storefront: dùng message `AsyncMessageInterface` hoặc scheduled task với `#[AsMessageHandler]` | `SitemapGenerateTaskHandler` của core |
| PERF-03 | NÊN | Thao tác dài từ Administration chia **đợt** có offset để không chạm `max_execution_time` | `DemoDataImporter::import()` |

## 11. Bảo mật — `SEC`

| Mã | Mức | Quy tắc |
|---|---|---|
| SEC-01 | BẮT BUỘC | SQL luôn dùng tham số; không nối chuỗi giá trị từ người dùng |
| SEC-02 | BẮT BUỘC | Mọi route Admin API có ACL (API-01); route storefront không trả dữ liệu Admin |
| SEC-03 | BẮT BUỘC | HTML do người vận hành nhập in ra qua bộ lọc Twig `sw_sanitize` hoặc `striptags` |
| SEC-04 | BẮT BUỘC | Bí mật (khoá API, tài khoản ngân hàng) đặt trong cấu hình plugin (`config.xml`, theo sales channel) hoặc biến môi trường — không trong code, không trong git |
| SEC-05 | BẮT BUỘC | Không ghi log dữ liệu cá nhân của khách (họ tên, SĐT, địa chỉ) |

## 12. Quan sát & lỗi — `OBS`

| Mã | Mức | Quy tắc |
|---|---|---|
| OBS-01 | BẮT BUỘC | Ghi log qua `Psr\Log\LoggerInterface` được inject; không `echo`, `var_dump`, `error_log` |
| OBS-02 | BẮT BUỘC | Lỗi trả cho client có **mã lỗi ổn định** (DATA-07) để Admin/ứng dụng ngoài xử lý theo mã, không theo câu chữ |
| OBS-03 | NÊN | Tích hợp ngoài (GHN, VNPay…) ghi log mỗi lần gọi thất bại kèm mã đơn — không kèm dữ liệu cá nhân (SEC-05) |

## 13. Kiểm thử — `TEST`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| TEST-01 | BẮT BUỘC | Mỗi plugin có `phpunit.xml` + `tests/`; integration test chạy trên DB riêng `shopware_test` qua `TestBootstrapper` | [runbook/06](runbook/06-tao-plugin.md) |
| TEST-02 | BẮT BUỘC | Có test cho: migration có thay đổi trạng thái (MIG-08), validation (DATA-06), loader/route có logic, mọi lỗi đã sửa | — |
| TEST-03 | BẮT BUỘC | Test integration **tự dựng dữ liệu** (không dựa vào dữ liệu demo hay plugin khác) — chứng minh plugin chạy một mình | `AcmeShipping` test không có `AcmeVietnam` |
| TEST-04 | BẮT BUỘC | Sửa giao diện → **chụp ảnh** storefront/Admin kiểm chứng; không tin `curl`/`grep` | [02-theme.md](02-theme.md) |
| TEST-05 | BẮT BUỘC | Thêm hoặc chuyển logic giữa plugin → chạy **phép thử tắt/bật** từng plugin | 08 · Quy tắc 5 |
| TEST-06 | BẮT BUỘC | Không đánh dấu ✅ trong roadmap khi chưa có test tự động và kiểm chứng thật | roadmap |
| TEST-07 | NÊN | Test đọc như ví dụ chạy được; data provider dùng `yield` có tên giải thích quy tắc | [09 · mục 4](09-nguyen-tac-ky-thuat.md) |

## 14. Phiên bản & triển khai — `REL`

| Mã | Mức | Quy tắc | Nguồn |
|---|---|---|---|
| REL-01 | BẮT BUỘC | `version` theo semver: thêm migration / tính năng → tăng minor; chỉ sửa lỗi → tăng patch; bỏ hoặc đổi bề mặt công khai → tăng major + ghi lộ trình | — |
| REL-02 | BẮT BUỘC | Commit đổi plugin ghi **các bước triển khai** cho môi trường khác (`plugin:update`, `dal:refresh:index`, `theme:compile`…). `plugin:update` chạy **từng plugin một lệnh** — nhiều plugin trong một lệnh sẽ hỏi xác nhận, chạy không tương tác thì `Aborted` (gặp 14/10/2026) | lịch sử commit Phase MB |
| REL-03 | BẮT BUỘC | Xoá/đổi tên class → `rm -rf var/cache/prod_*` trước khi chạy `bin/console` | sự cố rà soát 14/10 |
| REL-04 | BẮT BUỘC | Backup DB trước mọi thao tác thay đổi dữ liệu | [runbook/00-backup.md](runbook/00-backup.md) |

## 15. Định nghĩa "xong" (Definition of Done)

Một thay đổi plugin chỉ được coi là xong khi:

- [ ] Đúng plugin theo nghiệp vụ (ARCH-01), không phụ thuộc class plugin khác (ARCH-02)
- [ ] Đã tìm cơ chế native trước (ARCH-04), lý do tạo mới ghi trong docblock
- [ ] Migration: timestamp thật, idempotent, không ghi đè dữ liệu đã sửa, đã tăng `version` (MIG-01…07)
- [ ] Route Admin có ACL, route Store API theo mẫu Abstract + decorate (API-01, API-02)
- [ ] Nhãn trong snippet, CSS trong plugin sinh markup (SF-03, SF-04, ADM-01)
- [ ] Test tự động đạt (TEST-01…03); giao diện có ảnh chụp (TEST-04); chuyển logic có phép thử tắt/bật (TEST-05)
- [ ] `git status --short vendor/` rỗng; `composer.json` không đổi (trừ thư viện bên thứ ba)
- [ ] Tài liệu và roadmap cập nhật; commit ghi bước triển khai (REL-02)

---

## 16. Hiện trạng tuân thủ (14/10/2026, sau đợt refactor — cập nhật 22/10/2026)

| Plugin | Version | Test tự động | Ghi chú tuân thủ |
|---|---|---|---|
| `AcmeBrand` | 2.0.0 | 32 integration + unit | Đạt. 2.0.0 (22/10): bỏ toàn bộ phần storefront Twig (ADR-006), xoá SEO URL route storefront bằng migration. Store API thương hiệu (API-02) + SEO URL headless + sitemap headless; `BrandPageLoader` gọi lại route Store API. Route SEO storefront dùng `StorefrontFrameworkException` như route SEO của core (DATA-07); bản headless dùng `SeoException` của core — phần headless không phụ thuộc gói storefront. Test Store API gọi thẳng `HttpKernel::handle()` vì dự án không cài `symfony/browser-kit` |
| `AcmeCatalog` | 1.7.0 | 23 integration | Thứ tự giá trị thuộc tính đồng bộ mọi ngôn ngữ (chỉ nhóm còn mặc định — MIG-03). Đạt. Template SEO headless (`is_headless`) theo đúng quy ước storefront (ADR-005). Nhóm thuộc tính native "Chất liệu" + custom field `acme_model_size` (ARCH-04). Logic sinh biến thể tách khỏi lệnh sang service `VariantGenerator` (ARCH-07), lỗi miền `CatalogException` (DATA-07), bỏ association nạp thừa (PERF-01) |
| `AcmeShipping` | 1.0.0 | 3 integration | Đạt; test chạy một mình (TEST-03) |
| `AcmeVietnam` | 1.5.0 | 25 integration + unit | Đạt. 1.5.0 (22/10): bỏ domain giữ chỗ `default.headless*` để link email trỏ domain frontend. Tìm kiếm tiếng Việt (decorator analyzer/interpreter — EXT-01: core không có sự kiện ở bước này), channel headless, cấu hình tìm kiếm vi-VN, SĐT bắt buộc ở địa chỉ (sửa khoá sai của Phase 0), trạng thái đơn tiếng Việt. Test gọi migration trong transaction, **không** cài plugin vào DB test dùng chung (đổi tiền tệ, tắt quốc gia sẽ làm hỏng test plugin khác) |
| `AcmePayment` | 2.0.0 | 12 unit | Đạt. Store API thông tin chuyển khoản (1.3.0); 2.0.0 bỏ trang cảm ơn Twig (ADR-006). Payload VietQR so với chuỗi sinh bằng cài đặt CRC độc lập. Test bắt được lỗi thật: `iconv` trên macOS xoá "Đơ" khỏi nội dung chuyển khoản → đổi sang `UnicodeString::ascii()` |
| ~~`AcmeTheme`~~ | — | — | **Đã gỡ 22/10/2026** (ADR-006). `ListingMediaSubscriber` + wishlist chuyển sang `AcmeHeadless` (MIG-06) |
| `AcmeHeadless` | 1.2.0 | 15 integration | Đạt. Decorate `shop-settings` (API-02): logo, robots; migration robots, link email (so thẳng với `rawUrl` của core), wishlist — không ghi đè giá trị người vận hành (MIG-03) |
| `AcmeDemoData` | 1.2.1 | 13 unit | `position` giá trị thuộc tính ghi vào từng bản dịch (field dịch được của core). Lỗi miền `DemoDataException` kế thừa `HttpException`, mã `ACME_DEMO_DATA__*` (DATA-07). Chất liệu ghi bằng thuộc tính native thay cho custom field không ai định nghĩa. Còn: importer chưa có integration test |

Đợt refactor 14/10 còn sửa trên mọi plugin: `composer.json` đủ trường (STR-03: `require shopware/core ~6.7`, nhãn và mô tả `vi-VN`, `autoload-dev`), docblock class plugin ghi bề mặt công khai và hành vi tắt/gỡ (STR-06), `@internal` cho controller/command (STR-07), khoá snippet `acme-theme.*`, `acme-payment.*` (STR-02).

**Biết nhưng không sửa** (đã có lý do):

| Chỗ | Quy tắc | Lý do giữ |
|---|---|---|
| ~~`Acme\Theme\Migration\Migration1786166716EnableWishlist` ghi đè giá trị wishlist đã có~~ ✅ 22/10: thay bằng migration của `AcmeHeadless` không ghi đè | MIG-03 | Migration đã chạy — MIG-04 cấm sửa. Hiện giá trị là `true`, đúng ý kinh doanh |
| `Migration1786157044VietnamMarketBaseline` ném `\RuntimeException` khi thiếu quốc gia VN | DATA-07 | Migration đã chạy (MIG-04); lỗi chỉ xảy ra khi DB core hỏng, không phải lỗi nghiệp vụ |

**Nợ kỹ thuật cấp dự án:**

| Việc | Vì sao cần | Trạng thái |
|---|---|---|
| Phân tích tĩnh (PHPStan) cho `custom/plugins/` | Bắt lỗi kiểu, gọi method không tồn tại trước khi chạy — core Shopware chạy PHPStan mức cao | Chưa cài (`vendor/bin` không có) |
| Chuẩn định dạng code (PHP-CS-Fixer / ECS) | Thống nhất định dạng, giảm nhiễu khi review | Chưa cài |
| Kiểm tra class ma tự động | Bắt class không tồn tại | ✅ Lệnh trực tiếp ở [08](08-quy-tac-phat-trien.md#kiểm-tra-tự-động) — 165 class, 0 ma (14/10) · chưa chạy trong CI |
| CI chạy test mọi plugin | Test hiện chỉ chạy tay | Chưa có |
