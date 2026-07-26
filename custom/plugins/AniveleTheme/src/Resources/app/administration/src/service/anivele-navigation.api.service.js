const ApiService = Shopware.Classes.ApiService;
const { Application } = Shopware;

/**
 * Gọi các route Admin API của AniveleTheme.
 */
class AniveleNavigationApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/anivele/navigation') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'aniveleNavigationApiService';
    }

    list() {
        return this.httpClient
            .get(this.getApiBasePath(), { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    build(options = {}) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/build`, options, { headers: this.getBasicHeaders() })
            .then((response) => ApiService.handleResponse(response));
    }

    setVisibility(categoryId, visible) {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/visibility`,
                { categoryId, visible },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse(response));
    }

    addItem(parentId, name, type = 'page') {
        return this.httpClient
            .post(
                `${this.getApiBasePath()}/item`,
                { parentId, name, type },
                { headers: this.getBasicHeaders() },
            )
            .then((response) => ApiService.handleResponse(response));
    }
}

Application.addServiceProvider('aniveleNavigationApiService', (container) => {
    const initContainer = Application.getContainer('init');

    return new AniveleNavigationApiService(initContainer.httpClient, container.loginService);
});
