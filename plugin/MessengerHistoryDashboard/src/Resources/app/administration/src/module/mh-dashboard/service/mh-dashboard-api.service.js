const ApiService = Shopware.Classes.ApiService;

class MhDashboardApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'mh-dashboard');
    }

    listMessages({
        status = null,
        searchTerm = '',
        page = 1,
        limit = 25,
        topicGroup = '',
        createdFrom = '',
        createdTo = '',
        messageClass = '',
        transportName = '',
        businessReference = ''
    } = {}) {
        const params = new URLSearchParams();

        if (status) {
            params.set('status', status);
        }

        if (searchTerm) {
            params.set('query', searchTerm);
        }

        params.set('page', page);
        params.set('limit', limit);

        if (topicGroup) {
            params.set('topicGroup', topicGroup);
        }

        if (createdFrom) {
            params.set('createdFrom', createdFrom);
        }

        if (createdTo) {
            params.set('createdTo', createdTo);
        }

        if (messageClass) {
            params.set('messageClass', messageClass);
        }

        if (transportName) {
            params.set('transportName', transportName);
        }

        if (businessReference) {
            params.set('businessReference', businessReference);
        }

        const query = params.toString();

        return this.httpClient.get(`/_action/mh/messages${query ? `?${query}` : ''}`, {
            headers: this.getHeaders()
        });
    }

    loadMetrics() {
        return this.httpClient.get('/_action/mh/metrics', {
            headers: this.getHeaders()
        });
    }

    getMessageDetail(messageId) {
        return this.httpClient.get(`/_action/mh/messages/${encodeURIComponent(messageId)}`, {
            headers: this.getHeaders()
        });
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${this.loginService.getToken()}`,
            'Content-Type': 'application/json'
        };
    }

    retryMessage(messageId, reason = 'manual retry') {
        return this.httpClient.post(
            `/_action/mh/messages/${encodeURIComponent(messageId)}/retry`,
            { reason },
            { headers: this.getHeaders() }
        );
    }

    quarantineMessage(messageId, reason = 'manual quarantine') {
        return this.httpClient.post(
            `/_action/mh/messages/${encodeURIComponent(messageId)}/quarantine`,
            { reason },
            { headers: this.getHeaders() }
        );
    }

    dismissMessage(messageId, reason = 'manual dismiss') {
        return this.httpClient.post(
            `/_action/mh/messages/${encodeURIComponent(messageId)}/dismiss`,
            { reason },
            { headers: this.getHeaders() }
        );
    }

    cleanupMessages(days = 30) {
        return this.httpClient.post(
            '/_action/mh/messages/retention/cleanup',
            { days },
            { headers: this.getHeaders() }
        );
    }
}

Shopware.Service().register('mhDashboardApiService', () => {
    return new MhDashboardApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});
