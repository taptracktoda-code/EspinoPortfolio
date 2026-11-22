import type { SVGProps } from 'react';

const paths: Record<string, React.ReactNode> = {
    mark: <><path d="M12 2v20M2 12h20M5 5l14 14M19 5 5 19" /></>,
    grid: <><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></>,
    folder: <path d="M3 7V6a2 2 0 0 1 2-2h4l3 3h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>,
    award: <><circle cx="12" cy="9" r="5"/><path d="m8 13-2 8 6-3 6 3-2-8"/></>,
    work: <><rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/></>,
    school: <><path d="m2 9 10-5 10 5-10 5Zm4 2v5c3 3 9 3 12 0v-5m4-2v7"/></>,
    code: <path d="m8 6-6 6 6 6m8-12 6 6-6 6m-2-15-4 18"/>,
    file: <><path d="M6 3h8l4 4v14H6zM14 3v5h4M9 13h6m-6 4h6"/></>,
    mail: <><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></>,
    download: <path d="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4"/>,
    user: <><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v2"/></>,
    menu: <path d="M3 6h18M3 12h18M3 18h18"/>,
    close: <path d="m5 5 14 14M19 5 5 19"/>,
    plus: <path d="M12 4v16M4 12h16"/>,
    trophy: <><path d="M7 4h10v6a5 5 0 0 1-10 0Zm0 2H3v2a4 4 0 0 0 4 4m10-6h4v2a4 4 0 0 1-4 4m-5 3v5m-4 0h8"/></>,
};

export function Icon({ name, ...props }: SVGProps<SVGSVGElement> & { name: string }) {
    return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.65" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>{paths[name]}</svg>;
}
