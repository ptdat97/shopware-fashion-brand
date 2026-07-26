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

            deleteTarget: null,
            deleteChildNames: [],
            showDeleteModal: false,

            editTarget: null,
            editForm: { name: '', badge: '', linkType: '', link: '', linkNewTab: false },
            showEditModal: false,

            imageTarget: null,
            mediaList: [],
            isLoadingMedia: false,
            showImageModal: false,
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

        linkTypeOptions() {
            return [
                { value: '', label: this.$tc('anivele-navigation.edit.linkNone') },
                { value: 'external', label: this.$tc('anivele-navigation.edit.linkExternal') },
                { value: 'category', label: this.$tc('anivele-navigation.edit.linkCategory') },
                { value: 'product', label: this.$tc('anivele-navigation.edit.linkProduct') },
                { value: 'landing_page', label: this.$tc('anivele-navigation.edit.linkLandingPage') },
            ];
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

        /**
         * Gọi xoá không force trước. Backend chặn và trả danh sách con nếu có,
         * lúc đó mới mở modal xác nhận.
         */
        onDelete(item) {
            return this.aniveleNavigationApiService
                .deleteItem(item.id, false)
                .then((result) => {
                    if (result.needsConfirmation) {
                        this.deleteTarget = item;
                        this.deleteChildNames = result.childNames || [];
                        this.showDeleteModal = true;

                        return null;
                    }

                    this.createNotificationSuccess({
                        message: this.$tc('anivele-navigation.notification.deleted', 0, { name: item.name }),
                    });

                    return this.loadNavigation();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.deleteError'),
                    });
                });
        },

        onConfirmDelete() {
            const target = this.deleteTarget;

            if (!target) {
                return Promise.resolve();
            }

            return this.aniveleNavigationApiService
                .deleteItem(target.id, true)
                .then((result) => {
                    this.createNotificationSuccess({
                        message: this.$tc('anivele-navigation.notification.deletedWithChildren', 0, {
                            name: target.name,
                            count: result.deleted,
                        }),
                    });
                    this.onCloseDeleteModal();

                    return this.loadNavigation();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.deleteError'),
                    });
                });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
            this.deleteTarget = null;
            this.deleteChildNames = [];
        },

        onOpenImageModal(item) {
            this.imageTarget = item;
            this.showImageModal = true;

            if (this.mediaList.length > 0) {
                return Promise.resolve();
            }

            this.isLoadingMedia = true;

            return this.aniveleNavigationApiService
                .listMedia()
                .then((result) => {
                    this.mediaList = result.media || [];
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.mediaError'),
                    });
                })
                .finally(() => {
                    this.isLoadingMedia = false;
                });
        },

        onSelectImage(mediaId) {
            const target = this.imageTarget;

            if (!target) {
                return Promise.resolve();
            }

            return this.aniveleNavigationApiService
                .setImage(target.id, mediaId)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc(
                            mediaId
                                ? 'anivele-navigation.notification.imageSet'
                                : 'anivele-navigation.notification.imageCleared',
                            0,
                            { name: target.name },
                        ),
                    });
                    this.onCloseImageModal();

                    return this.loadNavigation();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.imageError'),
                    });
                });
        },

        onCloseImageModal() {
            this.showImageModal = false;
            this.imageTarget = null;
        },

        onOpenEditModal(item) {
            this.editTarget = item;
            this.editForm = {
                name: item.name || '',
                badge: item.badge || '',
                linkType: item.linkType || '',
                link: item.link || '',
                linkNewTab: !!item.linkNewTab,
            };
            this.showEditModal = true;
        },

        onCloseEditModal() {
            this.showEditModal = false;
            this.editTarget = null;
        },

        onSaveEdit() {
            const target = this.editTarget;

            if (!target) {
                return Promise.resolve();
            }

            return this.aniveleNavigationApiService
                .updateItem(target.id, {
                    name: this.editForm.name,
                    badge: this.editForm.badge,
                    linkType: this.editForm.linkType || null,
                    link: this.editForm.link,
                    linkNewTab: this.editForm.linkNewTab,
                })
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('anivele-navigation.notification.updated', 0, {
                            name: this.editForm.name || target.name,
                        }),
                    });
                    this.onCloseEditModal();

                    return this.loadNavigation();
                })
                .catch((error) => {
                    this.createNotificationError({
                        message: error.message || this.$tc('anivele-navigation.notification.updateError'),
                    });
                });
        },
    },
});
