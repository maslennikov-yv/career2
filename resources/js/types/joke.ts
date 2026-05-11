export type Joke = {
    id: number;
    type: string;
    setup: string;
    punchline: string;
    created_at: string;
};

export type JokesPagination = {
    data: Joke[];
    next_cursor: string | null;
    prev_cursor: string | null;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string | null;
    per_page: number;
};
