import { router } from '@inertiajs/react';
import { isAxiosError } from 'axios';
import { useEffect } from 'react';

const channelName = 'portfolio-updates';
const refreshHeader = 'X-Portfolio-Refresh';

export function notifyPortfolioChanged() {
    if (typeof BroadcastChannel === 'undefined') return;

    const channel = new BroadcastChannel(channelName);
    channel.postMessage('changed');
    channel.close();
}

export function usePortfolioRefresh() {
    useEffect(() => {
        const channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(channelName);
        let refreshing = false;
        let refreshPending = false;

        function refresh() {
            if (document.hidden || !navigator.onLine) return;
            if (refreshing) {
                refreshPending = true;
                return;
            }

            router.reload({
                headers: { [refreshHeader]: '1' },
                showProgress: false,
                onStart: () => { refreshing = true; },
                onSuccess: () => {
                    if (document.querySelector('meta[name="portfolio-offline"]')) window.location.reload();
                },
                onFinish: () => {
                    refreshing = false;
                    if (refreshPending) {
                        refreshPending = false;
                        refresh();
                    }
                },
            });
        }

        const stopInvalidListener = router.on('invalid', ({ detail: { response } }) => {
            if (!response.config.headers.get(refreshHeader)) return;
            if (response.status === 404 && window.location.pathname.startsWith('/projects/')) {
                window.location.replace('/');
            }
            return false;
        });
        const stopExceptionListener = router.on('exception', ({ detail: { exception } }) => {
            if (isAxiosError(exception) && exception.config?.headers.get(refreshHeader)) return false;
        });
        const timer = window.setInterval(() => { if (!refreshing) refresh(); }, 15000);
        channel?.addEventListener('message', refresh);
        document.addEventListener('visibilitychange', refresh);
        window.addEventListener('online', refresh);

        return () => {
            refreshPending = false;
            stopInvalidListener();
            stopExceptionListener();
            window.clearInterval(timer);
            channel?.close();
            document.removeEventListener('visibilitychange', refresh);
            window.removeEventListener('online', refresh);
        };
    }, []);
}
