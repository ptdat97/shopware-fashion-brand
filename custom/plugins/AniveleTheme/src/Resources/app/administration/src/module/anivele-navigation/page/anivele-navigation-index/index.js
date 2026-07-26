import template from './anivele-navigation-index.html.twig';
import './anivele-navigation-index.scss';

const { Component, Mixin } = Shopware;

Component.register('anivele-navigation-index', {
    template,

    inject: ['aniveleNavigationApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            isBuilding: false,
            menu: [],
            footer: [],
            depth: 0,
            buildMenu: true,
            buildFooter: true,
            buildImages: false,
            assignProducts: true,
            newItemName: '',
            newItemParentId: null,
            showAddModal: false,
        };
    },

    computed: {
        /** Cảnh báo khi depth < 3: flyout sẽ không xổ dù đã có danh mục con. */
        depthWarning() {
            return this.depth < 3;
        },

        addModalTitle() {
            return this.$tc('anivele-navigation.add.title');
        },
    },

    created() {
        this.loadNavigation();
    },

    methods: {
        loadNavigation() {
            this.isLoading = true;

            return this.aniveleNavigationApiService
                .list()
                .then((result) => {
                    this.menu = result.menu || [];
                    this.footer = result.footer || [];
                    this.depth = result.depth || 0;
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.loadError'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        onBuild() {
            this.isBuilding = true;

            return this.aniveleNavigationApiService
                .build({
                    menu: this.buildMenu,
                    footer: this.buildFooter,
                    images: this.buildImages,
                    assignProducts: this.assignProducts,
                })
                .then((result) => {
                    this.createNotificationSuccess({
                        message: this.$tc('anivele-navigation.notification.buildSuccess', 0, {
                            menu: result.menuCreated,
                            footer: result.footerCreated,
                        }),
                    });

                    // Sản phẩm không khớp từ khoá sẽ để danh mục rỗng — phải báo,
                    // không để người dùng tưởng đã xong.
                    if (result.unmatched && result.unmatched.length > 0) {
                        this.createNotificationWarning({
                            message: this.$tc('anivele-navigation.notification.unmatched', 0, {
                                count: result.unmatched.length,
                                names: result.unmatched.join(', '),
                            }),
                        });
                    }

                    return this.loadNavigation();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.buildError'),
                    });
                })
                .finally(() => {
                    this.isBuilding = false;
                });
        },

        onToggleVisibility(item) {
            const nextValue = !item.visible;

            return this.aniveleNavigationApiService
                .setVisibility(item.id, nextValue)
                .then(() => {
                    item.visible = nextValue;
                    this.createNotificationSuccess({
                        message: this.$tc(
                            nextValue
                                ? 'anivele-navigation.notification.shown'
                                : 'anivele-navigation.notification.hidden',
                            0,
                            { name: item.name },
                        ),
                    });
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.visibilityError'),
                    });
                });
        },

        onOpenAddModal(parentId) {
            this.newItemParentId = parentId;
            this.newItemName = '';
            this.showAddModal = true;
        },

        onCloseAddModal() {
            this.showAddModal = false;
            this.newItemParentId = null;
            this.newItemName = '';
        },

        onAddItem() {
            if (!this.newItemName.trim()) {
                return Promise.resolve();
            }

            return this.aniveleNavigationApiService
                .addItem(this.newItemParentId, this.newItemName.trim())
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('anivele-navigation.notification.itemAdded', 0, {
                            name: this.newItemName.trim(),
                        }),
                    });
                    this.onCloseAddModal();

                    return this.loadNavigation();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.addError'),
                    });
                });
        },
    },
});
