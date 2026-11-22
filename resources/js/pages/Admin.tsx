import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Icon } from '../Icons';
import type { Activity, Page, PageLink } from '../types';

const links = [
    ['Overview', 'dashboard', 'grid'], ['Profile', 'profile', 'user'], ['Projects', 'projects', 'folder'],
    ['Certificates', 'certificates', 'award'], ['Achievements', 'achievements', 'trophy'], ['Experience', 'experiences', 'work'],
    ['Education', 'educations', 'school'], ['Skills', 'skills', 'code'], ['Resume', 'resumes', 'file'],
    ['Social links', 'social-links', 'mark'], ['Client inbox', 'inquiries', 'mail'], ['Settings', 'settings', 'grid'],
    ['Audit logs', 'audit-logs', 'file'],
];

export function AdminLayout({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const page = usePage<{ notice?: string | null }>();
    const path = page.url.split('?')[0];
    return <div className="admin-layout">
        <a className="skip" href="#admin-main">Skip to content</a>
        <header className="admin-top">
            <a className="brand" href="/"><i><Icon name="mark"/></i> Owner dashboard</a>
            <button type="button" className="button secondary menu-toggle" aria-expanded={open} aria-controls="admin-nav" aria-label={open ? 'Close dashboard navigation' : 'Open dashboard navigation'} onClick={() => setOpen(!open)}><Icon name={open ? 'close' : 'menu'}/></button>
        </header>
        <aside className={open ? 'admin-side shown' : 'admin-side'}>
            <nav id="admin-nav" aria-label="Dashboard navigation" onKeyDown={event => { if (event.key === 'Escape') setOpen(false); }}>
                {links.map(([text, route, icon]) => {
                    const href = '/admin/' + route;
                    const current = path === href || path.startsWith(href + '/');
                    return <Link key={route} href={href} aria-current={current ? 'page' : undefined} onClick={() => setOpen(false)}><Icon name={icon}/>{text}</Link>;
                })}
                <a href="/">View portfolio</a>
                <Link href="/admin/logout" method="post" as="button" type="button">Sign out</Link>
            </nav>
        </aside>
        <main className="admin-main" id="admin-main">
            <div className="admin-heading"><h1>{title}</h1>{action}</div>
            {page.props.notice && <p role="status" className="notice">{page.props.notice}</p>}
            {children}
        </main>
    </div>;
}

export function Errors({ errors }: { errors: Record<string, string> }) {
    const messages = Object.values(errors);
    return messages.length > 0 ? <ul className="errors" role="alert">{messages.map((message, index) => <li key={index}>{message}</li>)}</ul> : null;
}

export function Pagination({ links }: { links: PageLink[] }) {
    if (links.length <= 3) return null;
    return <nav className="pagination" aria-label="Pages">
        {links.map((link, index) => {
            const label = index === 0 ? 'Previous' : index === links.length - 1 ? 'Next' : link.label;
            return link.url && !link.active
                ? <Link className="button secondary" key={index} href={link.url}>{label}</Link>
                : <span key={index} aria-current={link.active ? 'page' : undefined}>{label}</span>;
        })}
    </nav>;
}

export function when(value: string) {
    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

export function Login() {
    const form = useForm({ email: '', password: '' });
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/admin/login', { onFinish: () => form.reset('password') });
    }
    return <main className="login-screen">
        <div className="login-card">
            <a className="brand" href="/"><i><Icon name="mark"/></i> Personal Portfolio</a>
            <h1>Owner sign in</h1>
            <form onSubmit={submit}>
                <label>Email address<input autoComplete="username" type="email" required maxLength={254} value={form.data.email} onChange={e => form.setData('email', e.target.value)}/></label>
                <label>Password<input autoComplete="current-password" type="password" required maxLength={72} value={form.data.password} onChange={e => form.setData('password', e.target.value)}/></label>
                <Errors errors={form.errors}/>
                <button className="button primary" disabled={form.processing}>{form.processing ? 'Signing in…' : 'Sign in'}</button>
            </form>
            <a className="back-link" href="/">Back to portfolio</a>
        </div>
    </main>;
}

function ActivityList({ activity }: { activity: Activity[] }) {
    if (!activity.length) return <p className="muted">No administrative activity yet.</p>;
    return <ul className="activity">{activity.map(entry => <li key={entry.id}>
        <span><strong>{entry.action}</strong> · {entry.subject}{entry.subject_id ? ' #' + entry.subject_id : ''}</span>
        <time dateTime={entry.created_at}>{when(entry.created_at)}</time>
    </li>)}</ul>;
}

export function Dashboard({ stats, activity }: { stats: { label: string; count: number; href: string }[]; activity: Activity[] }) {
    return <AdminLayout title="Overview">
        <div className="stats">{stats.map(entry => <Link href={entry.href} key={entry.href}><strong>{entry.count}</strong><span>{entry.label}</span></Link>)}</div>
        <section className="admin-panel"><h2>Recent activity</h2><ActivityList activity={activity}/></section>
    </AdminLayout>;
}

export function Audit({ activity }: { activity: Page<Activity> }) {
    return <AdminLayout title="Audit logs"><div className="admin-panel"><ActivityList activity={activity.data}/></div><Pagination links={activity.links}/></AdminLayout>;
}
