export type Site = {
    id: number;
    name: string;
    domain: string | null;
    public_id: string;
    visits_count?: number;
    created_at: string | null;
};

export type HourlyPoint = {
    hour: string;
    uniques: number;
};

export type CityPoint = {
    city: string;
    visits: number;
};
