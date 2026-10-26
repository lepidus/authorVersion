// _submissions/viewsCount only knows about core views and exposes no hook for
// custom ones, so its response is intercepted to merge in the plugin counts.
(function patchViewsCountFetch() {
    if (window.__authorVersionFetchPatched) {
        return;
    }
    window.__authorVersionFetchPatched = true;

    const originalFetch = window.fetch.bind(window);
    const VIEWS_COUNT_PATH = '/_submissions/viewsCount';

    function fetchAuthorVersionCounts(viewsCountUrl) {
        const url = window.pkp?.plugins?.authorVersionViewsCountUrl;
        if (!url) {
            return Promise.resolve({});
        }
        const countsUrl = new URL(url, window.location.href);
        countsUrl.search = new URL(viewsCountUrl, window.location.href).search;
        return originalFetch(countsUrl, {
            headers: {Accept: 'application/json'},
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : {}))
            .catch(() => ({}));
    }

    window.fetch = async function (input, init) {
        const url = typeof input === 'string' ? input : input?.url || '';
        if (!url.includes(VIEWS_COUNT_PATH)) {
            return originalFetch(input, init);
        }

        const response = await originalFetch(input, init);
        try {
            const [data, authorVersionCounts] = await Promise.all([
                response.clone().json(),
                fetchAuthorVersionCounts(url),
            ]);
            const merged = {...data, ...authorVersionCounts};
            return new Response(JSON.stringify(merged), {
                status: response.status,
                statusText: response.statusText,
                headers: {'Content-Type': 'application/json'},
            });
        } catch (error) {
            return response;
        }
    };
})();
