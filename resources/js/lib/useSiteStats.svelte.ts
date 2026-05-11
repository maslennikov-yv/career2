import StatsController from '@/actions/App/Http/Controllers/StatsController';
import type { CityPoint, HourlyPoint } from '@/types';

type SiteStatsState = {
    hourly: HourlyPoint[];
    cities: CityPoint[];
    loading: boolean;
    error: string | null;
    siteId: number | null;
    hours: number | null;
};

type SiteStatsApi = {
    state: SiteStatsState;
    request: (siteId: number, hours: number) => void;
    cancel: () => void;
    reset: () => void;
};

const ERROR_MESSAGE = 'Не удалось загрузить статистику.';

export function createSiteStats(): SiteStatsApi {
    const state = $state<SiteStatsState>({
        hourly: [],
        cities: [],
        loading: false,
        error: null,
        siteId: null,
        hours: null,
    });

    let inflight: AbortController | null = null;
    let lastKey: string | null = null;

    const cancel = (): void => {
        inflight?.abort();
        inflight = null;
    };

    const reset = (): void => {
        cancel();
        state.hourly = [];
        state.cities = [];
        state.error = null;
        state.loading = false;
        state.siteId = null;
        state.hours = null;
        lastKey = null;
    };

    const fetchData = async (
        siteId: number,
        hours: number,
        controller: AbortController,
    ): Promise<void> => {
        const key = `${siteId}:${hours}`;
        const browserTimezone =
            Intl.DateTimeFormat().resolvedOptions().timeZone;
        const params = new URLSearchParams({
            hours: String(hours),
            timezone: browserTimezone,
        });
        const query = `?${params.toString()}`;

        try {
            const [hourlyRes, citiesRes] = await Promise.all([
                fetch(StatsController.hourly(siteId).url + query, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                }),
                fetch(StatsController.cities(siteId).url + query, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                }),
            ]);

            if (controller.signal.aborted) {
                return;
            }

            if (!hourlyRes.ok || !citiesRes.ok) {
                state.error = ERROR_MESSAGE;

                return;
            }

            const hourlyJson = await hourlyRes.json();
            const citiesJson = await citiesRes.json();

            if (controller.signal.aborted) {
                return;
            }

            state.hourly = hourlyJson.data ?? [];
            state.cities = citiesJson.data ?? [];
            state.siteId = siteId;
            state.hours = hours;

            lastKey = key;
        } catch (err) {
            if ((err as Error).name === 'AbortError') {
                return;
            }

            state.error = ERROR_MESSAGE;
        } finally {
            if (inflight === controller) {
                state.loading = false;
                inflight = null;
            }
        }
    };

    const request = (siteId: number, hours: number): void => {
        const key = `${siteId}:${hours}`;

        if (key === lastKey && state.error === null) {
            return;
        }

        inflight?.abort();
        state.error = null;
        state.loading = true;

        const controller = new AbortController();
        inflight = controller;
        void fetchData(siteId, hours, controller);
    };

    return { state, request, cancel, reset };
}
