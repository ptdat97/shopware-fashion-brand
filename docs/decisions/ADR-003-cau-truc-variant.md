# ADR-003: Cấu trúc variant cho catalog thời trang

**Trạng thái:** Đã chốt — 2 điểm còn treo (xem mục "Còn treo")
**Ngày:** 26/07/2026

## Bối cảnh

Hệ thống có 12 sản phẩm, **0 variant**. Size/Màu gắn dưới dạng `product_property` (nhãn mô tả) thay vì `product_option` + `product_configurator_setting` (trục sinh biến thể). Khách không chọn được size khi mua, tồn kho tính chung cho cả mẫu.

Cấu trúc variant chốt sai thì sửa rất đắt sau khi đã nhập hàng trăm SKU — nên ghi lại quyết định ở đây.

## Quyết định

### 1. Trục variant: Màu × Size

Chỉ hai trục. Chất liệu, Kiểu dáng, Hoạ tiết giữ nguyên là property mô tả + bộ lọc, **không** thành trục variant.

Lý do: mỗi trục nhân số SKU lên. Với 3 màu × 4 size = 12 SKU/mẫu — vừa tầm vận hành. Thêm trục Chất liệu (3 giá trị) thành 36 SKU/mẫu, quản lý tồn kho trở nên nặng nề mà giá trị bán hàng không tăng tương xứng.

### 2. Thang size theo loại hàng

Không dùng một thang chung cho mọi loại hàng:

| Loại hàng | Thang size | Ghi chú |
|---|---|---|
| Đầm, Áo, Bộ sưu tập | S, M, L, XL | Đang dùng, giữ nguyên |
| Chân váy, Quần | 26, 27, 28, 29, 30 | Theo số eo |

Cả hai thang cùng tồn tại trong nhóm **Kích cỡ**; mỗi sản phẩm chỉ gắn thang phù hợp với loại của nó.

Lý do chọn hai thang: catalog hiện tại có 7 đầm, 2 áo, 2 set, và 1 chân váy. Đầm/áo theo chữ là chuẩn phổ biến; quần/chân váy theo số eo chính xác hơn cho khách VN.

> Với 1 chân váy duy nhất hiện có (`ANV-1011`), thang số eo chỉ thực sự cần khi mở rộng dòng quần/chân váy. Nếu đến lúc nhập hàng thật vẫn không có quần, có thể hoãn thang số eo và dùng S–XL cho tất cả.

### 3. Giá khác nhau theo size

Cho phép. Mỗi variant là một row `product` riêng nên đặt giá riêng được sẵn, không cần cấu hình thêm.

Thực tế: chỉ đặt giá riêng cho size thực sự khác (thường XL), các size còn lại thừa kế giá của sản phẩm cha.

### 4. Quy tắc mã SKU

```
ANV-1001-DEN-M
│   │    │   └── size: S / M / L / XL, hoặc 26–30
│   │    └────── màu: 3 ký tự không dấu viết hoa
│   └─────────── mã mẫu: 4 chữ số, tăng dần
└─────────────── tiền tố thương hiệu
```

Mã màu 3 ký tự:

| Màu | Mã |
|---|---|
| Đen | DEN |
| Trắng | TRG |
| Kem | KEM |
| Be | BEI |
| Nâu | NAU |
| Xanh pastel | XPA |

Lý do dùng ký tự không dấu: SKU đi vào hoá đơn, file xuất, tem mã vạch — dấu tiếng Việt dễ hỏng khi qua hệ thống bên thứ ba (đơn vị vận chuyển, phần mềm kế toán).

### 5. Tách "Hoa nhiều màu" khỏi nhóm Màu sắc

"Hoa nhiều màu" là **hoạ tiết**, không phải màu. Để trong nhóm Màu sắc sẽ làm hỏng bộ lọc: khách lọc màu tím sẽ ra váy hoa.

Chuyển sang nhóm property mới **Hoạ tiết** (trơn / hoa nhí / kẻ sọc / hoạ tiết khác), dùng làm bộ lọc chứ không phải trục variant.

## Hệ quả

**Tích cực**

- Tồn kho đúng theo từng size — điều kiện để bán hàng thật
- Khách chọn được màu/size trên storefront
- Bộ lọc màu sạch, không lẫn hoạ tiết
- Không cần mua hay viết plugin nào: core 6.7 có sẵn variant generator, swatch màu, import/export

**Chi phí**

- Số row `product` tăng ~12 lần (3 màu × 4 size). Với 100 mẫu thật là ~1.200 SKU — vẫn nằm trong khả năng xử lý của Shopware
- Nhập liệu nặng hơn: phải dùng import CSV thay vì nhập tay
- Ảnh nên gắn theo màu, không theo từng size, để tránh nhân bản media không cần thiết

**Rủi ro chấp nhận**

- Nếu về sau cần trục variant thứ ba (ví dụ bán thêm chiều dài quần), phải sinh lại variant cho các mẫu liên quan. Chấp nhận vì khả năng xảy ra thấp với mặt hàng hiện tại.

## Còn treo

Hai điểm chưa chốt, **không chặn** việc bắt đầu:

1. **Free size** — có bán không? Nếu có, thêm một giá trị `Free size` vào nhóm Kích cỡ. Quyết định được bất cứ lúc nào trước khi nhập hàng thật.
2. **Thang số eo cho quần/chân váy** — chốt khi biết dòng hàng thật có quần hay không (xem ghi chú ở mục 2).

## Liên quan

- Đánh giá và thứ tự thực hiện: [../10-phase2-catalog.md](../10-phase2-catalog.md)
- Thiết kế catalog: [../05-catalog-fashion.md](../05-catalog-fashion.md)
