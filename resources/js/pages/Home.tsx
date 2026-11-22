import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { Icon } from '../Icons';
import type { Item, PublicData, Section } from '../types';
import { MediaPreview } from './MediaPreview';
import { isOfflineSnapshot, PublicLayout } from './PublicLayout';

function Empty({ section, icon }: { section: Section; icon: string }) {
    const subject = section.label === 'Experience' ? 'experience records' : section.label.toLowerCase();
    return <div className="empty"><Icon name={icon}/><h3>{section.empty}</h3><p>{section.label === 'Resume' ? 'A resume has yet to be published.' : 'No ' + subject + ' have been published yet. Please check back later.'}</p></div>;
}

function ProjectCards({ section }: { section: Section }) {
    if (!section.items.length) return <Empty section={section} icon="folder"/>;
    return <div className="card-grid">{section.items.map(item => <article key={item.id} className="project-card">
        <a className="project-cover" href={'/projects/' + item.slug} aria-label={'View ' + item.title}>
            {item.media[0] ? <img loading="lazy" src={item.media[0].url} alt={item.title + ' preview'}/> : <Icon name="folder"/>}
        </a>
        <div className="card-copy">
            <div className="tags">{item.details.featured && <span>Featured</span>}{item.details.category && <span>{item.details.category}</span>}{item.details.status && <span>{item.details.status}</span>}</div>
            <h3><a href={'/projects/' + item.slug}>{item.title}</a></h3>
            {item.details.overview && <p>{item.details.overview}</p>}
        </div>
    </article>)}</div>;
}

function date(value: string | boolean | null | undefined) {
    if (typeof value !== 'string') return '';
    return new Intl.DateTimeFormat('en', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(value + 'T00:00:00Z'));
}

function Timeline({ section, icon }: { section: Section; icon: string }) {
    if (!section.items.length) return <Empty section={section} icon={icon}/>;
    return <div className="timeline">{section.items.map(item => {
        const d = item.details;
        const period = [date(d.start), date(d.end)].filter(Boolean).join(' – ');
        return <article className="timeline-card" key={item.id}>
            {item.media[0]?.image && <img loading="lazy" width="48" height="48" src={item.media[0].url} alt=""/>}
            <div><h3>{item.title}</h3><p>{[d.organization, d.field, d.category, period].filter(Boolean).join(' · ')}</p>
                {[d.description, d.responsibilities, d.technologies, d.accomplishments].filter(Boolean).map((text, index) => <p className="preserve" key={index}>{text}</p>)}
            </div>
        </article>;
    })}</div>;
}

function CredentialCards({ section, icon }: { section: Section; icon: string }) {
    if (!section.items.length) return <Empty section={section} icon={icon}/>;
    return <div className="credential-list">{section.items.map(item => <article className="credential-card" key={item.id}>
        <h3>{item.title}</h3>
        <p>{[item.details.organization, item.details.category, date(item.details.issued || item.details.date)].filter(Boolean).join(' · ')}</p>
        {item.details.expires && <p>Expires {date(item.details.expires)}</p>}
        {item.details.description && <p className="preserve">{item.details.description}</p>}
        <MediaPreview item={item}/>
        {typeof item.details.verification === 'string' && item.details.verification && <a href={item.details.verification} target="_blank" rel="noreferrer">Verify certificate</a>}
    </article>)}</div>;
}

function Skills({ section }: { section: Section }) {
    if (!section.items.length) return <Empty section={section} icon="code"/>;
    const groups = new Map<string, Item[]>();
    for (const item of section.items) {
        const key = String(item.details.category || 'Other IT competencies');
        groups.set(key, [...(groups.get(key) || []), item]);
    }
    return <div className="skill-groups">{Array.from(groups, ([category, items]) => <article key={category} className="credential-card">
        <h3>{category}</h3><div className="tags">{items.map(item => <span key={item.id}>{item.title}</span>)}</div>
    </article>)}</div>;
}

function Socials({ section }: { section: Section }) {
    return <div className="social-links">{section.items.map(item => typeof item.details.url === 'string' && <a key={item.id} href={item.details.url} target="_blank" rel="noreferrer">{item.title}</a>)}</div>;
}

function InquiryForm({ types }: { types: string[] }) {
    const [ready, setReady] = useState(false);
    const [online, setOnline] = useState(() => navigator.onLine && !isOfflineSnapshot());
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    async function token(): Promise<string> {
        const response = await fetch('/contact/token', { headers: { Accept: 'application/json' }, cache: 'no-store' });
        if (!response.ok) throw new Error('The contact form is temporarily unavailable. Try again shortly.');
        return (await response.json()).token;
    }

    useEffect(() => {
        const updateNetwork = () => {
            setOnline(navigator.onLine);
            if (navigator.onLine) void token().then(() => setReady(true)).catch(() => setReady(false));
        };
        if (navigator.onLine && !isOfflineSnapshot()) {
            void token().then(() => setReady(true)).catch(() => setReady(false));
        }
        window.addEventListener('online', updateNetwork);
        window.addEventListener('offline', updateNetwork);
        return () => {
            window.removeEventListener('online', updateNetwork);
            window.removeEventListener('offline', updateNetwork);
        };
    }, []);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = event.currentTarget;
        setError('');
        setSuccess('');
        setBusy(true);
        try {
            const csrf = await token();
            const response = await fetch('/contact', {
                method: 'POST',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(Object.fromEntries(new FormData(form))),
            });
            if (response.status === 429) throw new Error('Too many submissions. Please try again later.');
            if (response.status === 419) throw new Error('Your form session expired. Please submit again.');
            if (response.status >= 500) throw new Error('The contact form is temporarily unavailable. Please try again.');
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values<string[]>(result.errors || {})[0]?.[0] || result.message || 'Could not send your inquiry.');
            form.reset();
            setSuccess(result.message);
        } catch (failure) {
            const detail = failure instanceof Error && !(failure instanceof TypeError) ? failure.message : 'Connection interrupted. Please try again.';
            setError(navigator.onLine ? detail : 'Connect to the internet to send your inquiry.');
        } finally {
            setBusy(false);
        }
    }

    return <form className="inquiry-form" onSubmit={event => void submit(event)}>
        <div className="field-pair"><label>Full name<input name="name" autoComplete="name" maxLength={120} required/></label>
            <label>Email address<input name="email" type="email" autoComplete="email" maxLength={254} required/></label></div>
        <label>Inquiry type<select name="type" defaultValue="" required><option value="" disabled>Select inquiry type</option>{types.map(type => <option key={type}>{type}</option>)}</select></label>
        <label>Subject<input name="subject" maxLength={160} required/></label>
        <label>Message<textarea name="message" rows={5} minLength={10} maxLength={6000} required/></label>
        <div className="trap" aria-hidden="true"><label>Website<input name="website" tabIndex={-1} autoComplete="off"/></label></div>
        <button type="submit" className="button primary" disabled={busy || !online}><Icon name="mail"/>{busy ? 'Sending…' : 'Send Message'}</button>
        {!online && <p role="status">Connect to the internet to send your inquiry.</p>}
        {online && !ready && <p className="form-hint">The form connects securely when you submit.</p>}
        {error && <p className="error" role="alert">{error}</p>}
        {success && <p className="success" role="status">{success}</p>}
    </form>;
}

function Group({ heading, children, id }: { heading: string; children: ReactNode; id?: string }) {
    return <section id={id} className="section"><div className="wrap"><h2>{heading}</h2>{children}</div></section>;
}

type HomeProps = PublicData & { sections: Record<string, Section>; inquiryTypes: string[] };

export default function Home({ sections: s, inquiryTypes, ...data }: HomeProps) {
    const { profile, photo } = data;
    const resume = s.resumes.items[0]?.media[0];
    return <PublicLayout {...data}>
        <section className="hero">
            <div className="wrap hero-layout">
                <div className="hero-copy">
                    <div className="eyebrow"><span/> PROFESSIONAL PORTFOLIO</div>
                    <h1>{profile.name || <>Personal<br className="hero-break"/> Portfolio<span>.</span></>}</h1>
                    {profile.title && <p className="hero-title">{profile.title}</p>}
                    <p className="hero-intro">{profile.intro || 'Explore my projects, credentials, and professional experience.'}</p>
                    {profile.objective && <p className="preserve">{profile.objective}</p>}
                    <div className="actions">
                        <a className="button primary" href="#projects"><Icon name="grid"/> View Projects</a>
                        {resume && <a className="button secondary" href={resume.url}><Icon name="download"/> Download Resume</a>}
                        <a className="button secondary" href="#contact"><Icon name="mail"/> Contact Me</a>
                    </div>
                    <Socials section={s['social-links']}/>
                </div>
                <div className="hero-art">
                    <div className="art-grid"/>
                    <div className="orbit orbit-outer"/><div className="orbit orbit-inner"/>
                    <span className="art-cross cross-one" aria-hidden="true">+</span><span className="art-cross cross-two" aria-hidden="true">+</span>
                    <span className="art-cross cross-three" aria-hidden="true">+</span><span className="art-cross cross-four" aria-hidden="true">+</span>
                    <div className="portrait">
                        {photo ? <img src={photo} width="640" height="640" alt={profile.name ? profile.name + '’s profile photo' : 'Portfolio owner profile photo'}/> :
                            <Icon name="user" role="img" aria-label="Default profile avatar" aria-hidden={undefined}/>}
                    </div>
                    <span className="art-coordinate" aria-hidden="true"/>
                </div>
            </div>
        </section>
        <Group id="projects" heading="Projects"><ProjectCards section={s.projects}/></Group>
        <Group id="about" heading="About Me">
            <div className="about-copy">{profile.bio ? <p className="preserve">{profile.bio}</p> : <p>My biography will appear here when it is published.</p>}
                {profile.interests && <><h3>Professional interests</h3><p className="preserve">{profile.interests}</p></>}
                {profile.objective && <><h3>Career objective</h3><p className="preserve">{profile.objective}</p></>}
            </div>
            <div className="split-sections">
                <section><h3 className="subheading">Experience</h3><Timeline section={s.experiences} icon="work"/></section>
                <section><h3 className="subheading">Education</h3><Timeline section={s.educations} icon="school"/></section>
            </div>
        </Group>
        <Group id="skills" heading="Technical Skills"><Skills section={s.skills}/></Group>
        <Group id="credentials" heading="Credentials">
            <div className="split-sections">
                <section><h3 className="subheading">Certificates</h3><CredentialCards section={s.certificates} icon="award"/></section>
                <section><h3 className="subheading">Achievements</h3><CredentialCards section={s.achievements} icon="trophy"/></section>
            </div>
        </Group>
        <Group id="resume" heading="Resume">
            {resume ? <a className="button secondary" href={resume.url}><Icon name="download"/> Download Resume</a> : <Empty section={s.resumes} icon="file"/>}
        </Group>
        <section id="contact" className="section contact-section">
            <div className="wrap contact-layout">
                <div><div className="eyebrow"><span/> CONTACT</div><h2>Let’s talk<span>.</span></h2><p>Have a project or opportunity in mind? Send me a message.</p>
                    {profile.email && <a className="email-link" href={'mailto:' + profile.email}><Icon name="mail"/>{profile.email}</a>}
                    <Socials section={s['social-links']}/>
                </div>
                <InquiryForm types={inquiryTypes}/>
            </div>
        </section>
    </PublicLayout>;
}
