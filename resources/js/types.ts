export type Values = Record<string, string | null>;
export type Fields = Record<string, [string, string, string?, string[]?]>;
export type Media = { id: number; image: boolean; url: string };
export type Item = {
    id: number;
    title: string;
    slug: string | null;
    published: boolean;
    details: Record<string, string | boolean | null>;
    media: Media[];
};
export type Section = { label: string; empty: string; items: Item[] };
export type PublicData = { site: Values; profile: Values; photo: string | null };
export type PageLink = { url: string | null; label: string; active: boolean };
export type Page<T> = { data: T[]; links: PageLink[]; total: number };
export type Activity = { id: number; action: string; subject: string; subject_id: number | null; created_at: string };
export type Inquiry = {
    id: number;
    name: string;
    email: string;
    type: string;
    subject: string;
    status: string;
    created_at: string;
    messages?: {
        id: number;
        direction: string;
        body: string;
        created_at: string;
        delivery?: { kind: string; status: string; attempts: number };
    }[];
};
