import type { Item, PublicData } from '../types';
import { MediaPreview } from './MediaPreview';
import { PublicLayout } from './PublicLayout';

export default function Project({ project, ...data }: PublicData & { project: Item }) {
    const d = project.details;
    const stack = typeof d.technologies === 'string' ? d.technologies.split(',').map(value => value.trim()).filter(Boolean) : [];
    return <PublicLayout {...data}><article className="wrap project-detail">
        <a className="back-link" href="/#projects">All projects</a>
        <div className="tags">{d.featured && <span>Featured</span>}{d.category && <span>{d.category}</span>}{d.status && <span>{d.status}</span>}</div>
        <h1>{project.title}</h1>
        {d.overview && <p className="hero-intro">{d.overview}</p>}
        {d.role && <p><strong>Role</strong> {d.role}</p>}
        <div className="actions">
            {([['github', 'GitHub'], ['demo', 'Live demo'], ['documentation', 'Documentation']] as const).map(([key, label]) =>
                typeof d[key] === 'string' && d[key] && <a key={key} className="button secondary" href={d[key]} target="_blank" rel="noreferrer">{label}</a>)}
        </div>
        {d.description && <section><h2>About the project</h2><p className="preserve">{d.description}</p></section>}
        {stack.length > 0 && <section><h2>Technology stack</h2><div className="tags">{stack.map((value, index) => <span key={index}>{value}</span>)}</div></section>}
        {d.features && <section><h2>Features</h2><p className="preserve">{d.features}</p></section>}
        {project.media.length > 0 && <section><h2>Screenshots</h2><MediaPreview item={project}/></section>}
    </article></PublicLayout>;
}
