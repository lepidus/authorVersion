// The core _submissions/viewsCount has no hook for plugin views, so its response is extended here.
(function patchViewsCountFetch() {
    if (window.__authorVersionFetchPatched) {
        return;
    }
    window.__authorVersionFetchPatched = true;

    const originalFetch = window.fetch.bind(window);
    const VIEWS_COUNT_PATH = '/_submissions/viewsCount';
    const AUTHOR_VERSION_VIEWS_COUNT_PATH = '/_submissions/authorVersionViewsCount';

    function viewsCountUrl(input) {
        const url = new URL(typeof input === 'string' ? input : input?.url || '', window.location.href);
        if (!url.pathname.endsWith(VIEWS_COUNT_PATH)) {
            return null;
        }
        const roles = url.searchParams.getAll('assignedWithRoles[]').map(Number);
        return roles.includes(pkp.const.ROLE_ID_REVIEWER) ? null : url;
    }

    function fetchAuthorVersionCounts(url, signal) {
        url.pathname = url.pathname.slice(0, -VIEWS_COUNT_PATH.length) + AUTHOR_VERSION_VIEWS_COUNT_PATH;
        return originalFetch(url, {
            headers: {Accept: 'application/json'},
            credentials: 'same-origin',
            signal,
        })
            .then((response) => (response.ok ? response.json() : {}))
            .catch((error) => {
                if (signal?.aborted) {
                    throw error;
                }
                return {};
            });
    }

    window.fetch = async function (input, init) {
        const url = viewsCountUrl(input);
        if (!url) {
            return originalFetch(input, init);
        }

        const [response, authorVersionCounts] = await Promise.all([
            originalFetch(input, init),
            fetchAuthorVersionCounts(url, init?.signal),
        ]);
        if (!response.ok) {
            return response;
        }
        const data = await response.json();
        return new Response(JSON.stringify({...data, ...authorVersionCounts}), {
            status: response.status,
            statusText: response.statusText,
            headers: {'Content-Type': 'application/json'},
        });
    };
})();
