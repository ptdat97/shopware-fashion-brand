/**
 * Thẻ "Thương hiệu" trong trang chi tiết nhà sản xuất (Catalogues → Manufacturers).
 *
 * ⚠️ File này viết tay, KHÔNG do Vite sinh ra — cùng cách với AcmeDemoData, xem
 * docs/runbook/05-demo-data.md mục "JS của Administration viết tay".
 * Không tạo src/Resources/app/administration/src/main.js: lần build admin đầu
 * tiên sẽ ghi đè thư mục này.
 *
 * Sau khi sửa file này phải chạy: bin/console assets:install
 *
 * Dữ liệu brand nằm ở `manufacturer.extensions.acmeBrand` (quan hệ 1–1, autoload).
 * Meta SEO KHÔNG nằm ở đây: đó là custom field dịch được của nhà sản xuất, hiện trong
 * thẻ custom field có sẵn của core ngay bên dưới — không vẽ lại ô nhập.
 * Manufacturer chưa có bản ghi brand thì tạo sẵn một bản trong bộ nhớ; nó chỉ được
 * ghi xuống khi người dùng sửa ít nhất một trường — changeset của core bỏ qua
 * entity mới không đổi gì.
 */

const { Component, Locale, Data } = Shopware;
const { Criteria } = Data;

const BRAND_CODE_PATTERN = /^[A-Z0-9]{3}$/;

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'product_manufacturer',
    roles: {
        viewer: {
            privileges: ['acme_brand:read', 'cms_page:read'],
            dependencies: [],
        },
        editor: {
            privileges: ['acme_brand:create', 'acme_brand:update'],
            dependencies: [],
        },
        deleter: {
            privileges: ['acme_brand:delete'],
            dependencies: [],
        },
    },
});

Component.override('sw-manufacturer-detail', {
    template: `
{% block sw_manufacturer_detail_base_basic_info_card %}
{% parent %}

<mt-card
    v-if="brand"
    class="acme-brand-card"
    position-identifier="acme-brand-manufacturer-detail"
    :title="$t('acme-brand.card.title')"
    :subtitle="$t('acme-brand.card.subtitle')"
>
    <sw-container
        columns="repeat(auto-fit, minmax(250px, 1fr))"
        gap="0 30px"
    >
        <mt-switch
            v-model="brand.active"
            name="acme-brand-active"
            bordered
            :label="$t('acme-brand.field.active')"
            :help-text="$t('acme-brand.help.active')"
            :disabled="!canEditBrand || undefined"
        />

        <mt-switch
            v-model="brand.featured"
            name="acme-brand-featured"
            bordered
            :label="$t('acme-brand.field.featured')"
            :disabled="!canEditBrand || undefined"
        />

        <mt-text-field
            :model-value="brand.code"
            name="acme-brand-code"
            maxlength="3"
            :label="$t('acme-brand.field.code')"
            :placeholder="$t('acme-brand.placeholder.code')"
            :help-text="$t('acme-brand.help.code')"
            :error="brandCodeError"
            :disabled="!canEditBrand || undefined"
            @update:model-value="onChangeBrandCode"
        />

        <mt-number-field
            v-model="brand.position"
            name="acme-brand-position"
            number-type="int"
            placeholder="0"
            :label="$t('acme-brand.field.position')"
            :help-text="$t('acme-brand.help.position')"
            :disabled="!canEditBrand || undefined"
        />
    </sw-container>

    <sw-upload-listener
        :upload-tag="bannerUploadTag"
        auto-upload
        @media-upload-finish="onSetBanner"
    />
    <sw-media-upload-v2
        class="acme-brand-card__banner"
        style="margin-bottom: var(--scale-size-24, 24px)"
        :source="brand.bannerMediaId"
        :allow-multi-select="false"
        variant="regular"
        :upload-tag="bannerUploadTag"
        :label="$t('acme-brand.field.banner')"
        default-folder="product_manufacturer"
        :disabled="!canEditBrand || undefined"
        @sw-media-upload-v2-media-upload-success="onSetBanner"
        @media-drop="onDropBanner"
        @media-upload-sidebar-open="showBannerModal = true"
        @media-upload-remove-image="brand.bannerMediaId = null"
    />
    <sw-media-modal-v2
        v-if="showBannerModal"
        :allow-multi-select="false"
        entity-context="product_manufacturer"
        @modal-close="showBannerModal = false"
        @media-modal-selection-change="onBannerSelectionChange"
    />

    <sw-entity-single-select
        v-model:value="brand.cmsPageId"
        entity="cms_page"
        class="acme-brand-card__layout"
        show-clearable-button
        :criteria="cmsPageCriteria"
        :label="$t('acme-brand.field.cmsPage')"
        :help-text="$t('acme-brand.help.cmsPage')"
        :disabled="!canEditBrand || undefined"
    />

</mt-card>
{% endblock %}
`,

    data() {
        return {
            showBannerModal: false,
        };
    },

    computed: {
        brand() {
            return this.manufacturer?.extensions?.acmeBrand ?? null;
        },

        brandRepository() {
            return this.repositoryFactory.create('acme_brand');
        },

        canEditBrand() {
            return this.acl.can('product_manufacturer.editor');
        },

        bannerUploadTag() {
            return `acme-brand-banner--${this.manufacturer?.id}`;
        },

        cmsPageCriteria() {
            const criteria = new Criteria(1, 25);
            // Chỉ layout kiểu landing page: trang brand tự gắn danh sách sản phẩm của brand
            // phía dưới. Layout kiểu listing có khối product-listing riêng — trên trang brand
            // khối đó sẽ không biết lọc theo brand nào.
            criteria.addFilter(Criteria.equals('type', 'landingpage'));

            return criteria;
        },

        brandCodeError() {
            const code = this.brand?.code;
            if (!code || BRAND_CODE_PATTERN.test(code)) {
                return null;
            }

            return { detail: this.$t('acme-brand.error.code') };
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            // Manufacturer mới: được tạo đồng bộ trong createdComponent của core
            if (!this.manufacturerId) {
                this.ensureBrand();
            }
        },

        async loadEntityData() {
            await this.$super('loadEntityData');
            this.ensureBrand();
        },

        ensureBrand() {
            if (!this.manufacturer || this.manufacturer.extensions?.acmeBrand) {
                return;
            }

            const brand = this.brandRepository.create();
            brand.productManufacturerId = this.manufacturer.id;

            this.manufacturer.extensions.acmeBrand = brand;
        },

        onChangeBrandCode(value) {
            // Mã brand là tiền tố SKU — luôn lưu in hoa, bỏ khoảng trắng
            const normalized = (value ?? '').toUpperCase().replace(/\s/g, '');
            const code = normalized === '' ? null : normalized;

            if (code === this.brand.code && value !== code) {
                // Giá trị chuẩn hoá không đổi (gõ "nke" khi đã là "NKE") thì Vue không
                // vẽ lại ô nhập và chữ thường còn nguyên trên màn hình. Đổi tạm sang
                // đúng thứ vừa gõ rồi trả về giá trị chuẩn để ép vẽ lại.
                this.brand.code = value;
                this.$nextTick(() => {
                    this.brand.code = code;
                });
                return;
            }

            this.brand.code = code;
        },

        onSetBanner({ targetId }) {
            this.brand.bannerMediaId = targetId;
        },

        onDropBanner(dragData) {
            this.brand.bannerMediaId = dragData.id;
        },

        onBannerSelectionChange([media]) {
            this.brand.bannerMediaId = media.id;
        },
    },
});

Locale.extend('vi-VN', {
    'acme-brand': {
        card: {
            title: 'Thương hiệu',
            subtitle: 'Trang thương hiệu trên storefront: /brands/…',
        },
        field: {
            active: 'Hiển thị trang thương hiệu',
            featured: 'Thương hiệu nổi bật',
            code: 'Mã thương hiệu',
            position: 'Thứ tự hiển thị',
            banner: 'Banner',
            cmsPage: 'Layout trang thương hiệu',
        },
        placeholder: {
            code: 'VD: NKE',
        },
        help: {
            active: 'Tắt chỉ ẩn trang thương hiệu. Sản phẩm của thương hiệu vẫn hiển thị và vẫn bán.',
            code: '3 ký tự in hoa, dùng làm tiền tố mã SKU (NKE-1001-01). Duy nhất toàn hệ thống.',
            position: 'Số nhỏ đứng trước trong danh sách /brands.',
            cmsPage: 'Layout kiểu Landing page, hiện phía trên danh sách sản phẩm. Bỏ trống nếu không cần.',
        },
        error: {
            code: 'Mã thương hiệu phải gồm đúng 3 chữ in hoa hoặc chữ số.',
        },
    },
});

Locale.extend('en-GB', {
    'acme-brand': {
        card: {
            title: 'Brand',
            subtitle: 'Brand page on the storefront: /brands/…',
        },
        field: {
            active: 'Show brand page',
            featured: 'Featured brand',
            code: 'Brand code',
            position: 'Position',
            banner: 'Banner',
            cmsPage: 'Brand page layout',
        },
        placeholder: {
            code: 'e.g. NKE',
        },
        help: {
            active: 'Disabling only hides the brand page. Products of the brand stay visible and purchasable.',
            code: '3 uppercase characters, used as SKU prefix (NKE-1001-01). Unique across the shop.',
            position: 'Lower numbers come first on /brands.',
            cmsPage: 'Landing page layout, shown above the product list. Leave empty if not needed.',
        },
        error: {
            code: 'Brand code must be exactly 3 uppercase letters or digits.',
        },
    },
});
