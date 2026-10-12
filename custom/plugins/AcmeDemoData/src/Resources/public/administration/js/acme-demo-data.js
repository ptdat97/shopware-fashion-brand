/**
 * Nút "Nhập dữ liệu demo" trong trang cấu hình plugin.
 *
 * ⚠️ File này viết tay, KHÔNG do Vite sinh ra.
 *
 * Administration nạp JS của plugin bằng <script type="module"> (xem
 * Shopware\Administration\Framework\Api\Subscriber\AdminInfoConfigBundlesSubscriber
 * và src/core/application.ts::injectPlugin), nên một ES module thuần chỉ dùng biến
 * toàn cục `Shopware` chạy được ngay mà không cần build. Dự án chưa cài node_modules
 * cho `bin/build-administration.sh` (~1 GB phụ thuộc npm) nên đây là đường ngắn nhất.
 *
 * Vì vậy: **không tạo src/Resources/app/administration/src/main.js**. Có file đó thì
 * lần chạy build admin đầu tiên sẽ ghi đè thư mục này.
 *
 * Sau khi sửa file này phải chạy: bin/console assets:install
 */

const { Component, Application, Mixin, Classes } = Shopware;
const { ApiService } = Classes;

/** Gọi hai route của Acme\DemoData\Controller\DemoDataController */
class AcmeDemoDataApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'acme-demo-data') {
        // Controller trả JSON thường, không phải JSON:API
        super(httpClient, loginService, apiEndpoint, 'application/json');
    }

    getStatus() {
        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/status`, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    /** Nạp một đợt; trả về offset để gọi tiếp cho tới khi done = true */
    importChunk(offset) {
        return this.httpClient
            .post(`/_action/${this.getApiBasePath()}/import`, { offset }, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }
}

Application.addServiceProvider('acmeDemoDataService', (container) => {
    const initContainer = Application.getContainer('init');

    return new AcmeDemoDataApiService(initContainer.httpClient, container.loginService);
});

Component.register('acme-demo-data-import', {
    template: `
{% block acme_demo_data_import %}
<div class="acme-demo-data-import">
    <mt-banner
        v-if="blocker"
        variant="critical"
        title="Chưa nhập được"
    >
        {{ blocker }}
    </mt-banner>

    <p
        v-if="status"
        class="acme-demo-data-import__status"
    >
        Ảnh đã nạp: <strong>{{ status.mediaImported }}/{{ status.mediaTotal }}</strong>
        &nbsp;·&nbsp;
        Sản phẩm demo: <strong>{{ status.productsImported }}/{{ status.productsTotal }}</strong>
    </p>

    <div
        v-if="isImporting"
        class="acme-demo-data-import__progress"
    >
        <mt-progress-bar
            :model-value="progress"
            :max-value="100"
            :label="step"
        />
    </div>

    <mt-button
        class="acme-demo-data-import__button"
        variant="primary"
        :disabled="isLoading || isImporting || !!blocker"
        :is-loading="isImporting"
        @click="onImport"
    >
        {{ buttonLabel }}
    </mt-button>

    <mt-banner
        v-if="warnings.length"
        class="acme-demo-data-import__warnings"
        variant="attention"
        title="Có mục bị bỏ qua"
    >
        <ul>
            <li
                v-for="(warning, index) in warnings"
                :key="index"
            >
                {{ warning }}
            </li>
        </ul>
    </mt-banner>
</div>
{% endblock %}
    `,

    inject: ['acmeDemoDataService'],

    mixins: [Mixin.getByName('notification')],

    // sw-form-field-renderer luôn truyền value/error vào, khai báo để Vue không đẩy xuống DOM
    props: {
        value: {
            type: [String, Number, Boolean, Object, Array],
            required: false,
            default: null,
        },
        error: {
            type: Object,
            required: false,
            default: null,
        },
    },

    emits: ['update:value'],

    data() {
        return {
            isLoading: true,
            isImporting: false,
            status: null,
            blocker: null,
            progress: 0,
            step: '',
            warnings: [],
        };
    },

    computed: {
        buttonLabel() {
            if (this.isImporting) {
                return 'Đang nhập…';
            }

            // Đã có sẵn dữ liệu thì lần bấm sau là cập nhật, nói rõ để khỏi sợ nhân bản
            if (this.status && this.status.productsImported > 0) {
                return 'Nhập lại dữ liệu demo';
            }

            return 'Nhập dữ liệu demo';
        },
    },

    created() {
        this.loadStatus();
    },

    methods: {
        async loadStatus() {
            this.isLoading = true;

            try {
                const status = await this.acmeDemoDataService.getStatus();
                this.status = status;
                this.blocker = status.ready ? null : status.blocker;
            } catch (error) {
                this.blocker = this.readError(error);
            } finally {
                this.isLoading = false;
            }
        },

        async onImport() {
            this.isImporting = true;
            this.warnings = [];
            this.progress = 0;
            this.step = 'Đang chuẩn bị…';

            let offset = 0;
            let products = 0;

            try {
                for (;;) {
                    const result = await this.acmeDemoDataService.importChunk(offset);

                    if (result.warnings && result.warnings.length) {
                        this.warnings = [...this.warnings, ...result.warnings];
                    }

                    this.step = result.step;
                    this.progress = result.total > 0
                        ? Math.round((result.offset / result.total) * 100)
                        : 0;

                    if (result.done) {
                        products = result.products;
                        break;
                    }

                    // Chốt chặn: offset không tiến thì dừng, tránh vòng lặp vô hạn
                    if (result.offset <= offset) {
                        throw new Error('Tiến trình nhập không tiến triển, đã dừng lại.');
                    }

                    offset = result.offset;
                }

                this.createNotificationSuccess({
                    message: `Đã nhập xong ${products} sản phẩm demo.`,
                });
            } catch (error) {
                this.createNotificationError({
                    message: this.readError(error),
                });
            } finally {
                this.isImporting = false;
                this.progress = 0;
                await this.loadStatus();
            }
        },

        /** Gỡ thông điệp lỗi từ cả hai dạng: JsonResponse của plugin và lỗi chuẩn Shopware */
        readError(error) {
            const data = error && error.response && error.response.data;

            if (data && Array.isArray(data.errors) && data.errors.length) {
                return data.errors[0].detail || data.errors[0].title;
            }

            if (data && data.message) {
                return data.message;
            }

            return (error && error.message) || 'Lỗi không xác định';
        },
    },
});
