import { Link, router, useForm, usePoll } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import type { Inquiry, Page } from '../types';
import { AdminLayout, Errors, Pagination, when } from './Admin';

export function Inbox({ inquiries, filter, statuses }: { inquiries: Page<Inquiry>; filter: string | null; statuses: string[] }) {
    return <AdminLayout title="Client inbox">
        <section className="admin-panel">
            <label className="status-filter">Filter by status<select value={filter || ''} onChange={e => router.get('/admin/inquiries', e.target.value ? { status: e.target.value } : {})}>
                <option value="">All inquiries</option>{statuses.map(status => <option key={status}>{status}</option>)}
            </select></label>
            {!inquiries.data.length ? <p className="muted">No inquiries in this view.</p> :
                <ul className="record-list inbox-list">{inquiries.data.map(inquiry => <li key={inquiry.id}>
                    <Link href={'/admin/inquiries/' + inquiry.id}><strong>{inquiry.subject}</strong><span>{inquiry.name} · {inquiry.type}</span></Link>
                    <div><span className={inquiry.status === 'New' ? 'status published' : 'status'}>{inquiry.status}</span><time dateTime={inquiry.created_at}>{when(inquiry.created_at)}</time></div>
                </li>)}</ul>}
            <Pagination links={inquiries.links}/>
        </section>
    </AdminLayout>;
}

export function Thread({ inquiry, statuses }: { inquiry: Inquiry; statuses: string[] }) {
    const route = '/admin/inquiries/' + inquiry.id;
    const reply = useForm({ message: '' });
    const status = useForm({ status: inquiry.status });
    const pending = inquiry.messages?.some(message => ['Queued', 'Retrying'].includes(message.delivery?.status || ''));

    useEffect(() => status.setData('status', inquiry.status), [inquiry.status]);

    function send(event: FormEvent) {
        event.preventDefault();
        reply.post(route + '/reply', { preserveScroll: true, onSuccess: () => reply.reset() });
    }
    function update(event: FormEvent) {
        event.preventDefault();
        status.patch(route + '/status', { preserveScroll: true });
    }
    return <AdminLayout title={inquiry.subject}>
        {pending && <DeliveryPoll/>}
        <Link className="back-link" href="/admin/inquiries">All inquiries</Link>
        <section className="admin-panel thread-meta">
            <div><h2>{inquiry.name}</h2><p><a href={'mailto:' + inquiry.email}>{inquiry.email}</a></p><p>{inquiry.type}</p></div>
            <form onSubmit={update}>
                <label>Status<select value={status.data.status} onChange={e => status.setData('status', e.target.value)}>{statuses.map(value => <option key={value}>{value}</option>)}</select></label>
                <Errors errors={status.errors}/>
                <button className="button secondary" disabled={status.processing}>Save status</button>
            </form>
        </section>
        <section className="thread-messages" aria-label="Conversation history">
            {inquiry.messages?.map(message => <article className="admin-panel" key={message.id}>
                <div className="panel-heading"><h2>{message.direction === 'incoming' ? inquiry.name : 'Owner reply'}</h2><time dateTime={message.created_at}>{when(message.created_at)}</time></div>
                <p className="preserve">{message.body}</p>
                {message.delivery && <p className="delivery"><span className="status">{message.delivery.kind === 'notification' ? 'Owner notification' : 'Client email'} · {message.delivery.status}</span> {message.delivery.attempts > 0 && <span>{message.delivery.attempts} {message.delivery.attempts === 1 ? 'attempt' : 'attempts'}</span>}</p>}
            </article>)}
        </section>
        <section className="admin-panel"><h2>Reply by email</h2>
            <form onSubmit={send}>
                <label>Reply to {inquiry.email}<textarea minLength={1} maxLength={6000} rows={6} required value={reply.data.message} onChange={e => reply.setData('message', e.target.value)}/></label>
                <Errors errors={reply.errors}/>
                <button className="button primary" disabled={reply.processing}>Send reply</button>
            </form>
        </section>
    </AdminLayout>;
}

function DeliveryPoll() {
    usePoll(3500, { only: ['inquiry'] });

    return null;
}
