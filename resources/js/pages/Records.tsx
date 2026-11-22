import { useEffect, useRef, useState, type FormEvent } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Icon } from '../Icons';
import type { Fields, Item, PageLink, Values } from '../types';
import { AdminLayout, Errors, Pagination } from './Admin';

type Draft = Record<string, string | boolean>;
type Spec = { label: string; item: string; empty: string; fields: Fields; upload?: [string, string, number, number] };

function fieldValues(fields: Fields, data?: Item['details'] | Values): Draft {
    return Object.fromEntries(Object.entries(fields).map(([key, field]) => [
        key, field[1] === 'checkbox' ? Boolean(data?.[key]) : String(data?.[key] ?? (field[1] === 'color' ? '#ff875c' : '')),
    ]));
}

function FieldsEditor({ fields, values, onChange, categories }: { fields: Fields; values: Draft; onChange: (data: Draft) => void; categories?: string[] }) {
    return <div className="dynamic-fields">{Object.entries(fields).map(([key, [label, type, requirement, options]]) => {
        const value = String(values[key] ?? '');
        const required = requirement === 'required';
        const change = (next: string | boolean) => onChange({ ...values, [key]: next });
        if (type === 'checkbox') return <label className="check" key={key}><input type="checkbox" checked={Boolean(values[key])} onChange={e => change(e.target.checked)}/>{label}</label>;
        return <label key={key}>{label}
            {type === 'textarea' ? <textarea required={required} rows={4} maxLength={6000} value={value} onChange={e => change(e.target.value)}/>
                : type === 'select' ? <select required={required} value={value} onChange={e => change(e.target.value)}><option value="">Select {label.toLowerCase()}</option>{options?.map(option => <option key={option}>{option}</option>)}</select>
                : type === 'category' ? <><input list="skill-categories" required={required} maxLength={100} value={value} onChange={e => change(e.target.value)}/>
                    <datalist id="skill-categories">{categories?.map(category => <option key={category} value={category}/>)}</datalist></>
                : <input type={type} required={required} maxLength={type === 'email' ? 254 : 500} value={value} onChange={e => change(e.target.value)}/>}
        </label>;
    })}</div>;
}

function RecordEditor({ section, spec, item, categories, close }: { section: string; spec: Spec; item?: Item; categories: string[]; close: () => void }) {
    const [values, setValues] = useState(fieldValues(spec.fields, item?.details));
    const form = useForm({ title: item?.title || '', published: item?.published || false, files: [] as File[], remove_media: [] as number[] });
    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform(data => ({ ...data, ...values }));
        form.post('/admin/' + section + (item ? '/' + item.id : ''), { forceFormData: true, preserveScroll: true, onSuccess: close });
    }
    return <section className="admin-panel editor" aria-labelledby="record-editor">
        <div className="panel-heading"><h2 id="record-editor">{item ? 'Edit ' : 'Add '}{spec.item.toLowerCase()}</h2><button type="button" className="button secondary" onClick={close}>Cancel</button></div>
        <form onSubmit={submit}>
            <label>{spec.item === 'Degree' || spec.item === 'Position' || spec.item === 'Skill' ? spec.item : 'Title'}<input required maxLength={160} value={form.data.title} onChange={e => form.setData('title', e.target.value)}/></label>
            <FieldsEditor fields={spec.fields} values={values} onChange={setValues} categories={categories}/>
            {spec.upload && <>
                <label>{spec.upload[0]}<input type="file" accept={spec.upload[1].split(',').map(ext => '.' + ext).join(',')} multiple={spec.upload[3] > 1}
                    onChange={event => form.setData('files', Array.from(event.target.files || []))}/><span className="form-hint">Up to {spec.upload[3]} {spec.upload[3] === 1 ? 'file' : 'files'}, {spec.upload[2] / 1024} MB each.{spec.upload[3] === 1 && item?.media.length ? ' Selecting a new file replaces the current file.' : ''}</span></label>
                {item?.media.map((file, index) => <div className="existing-file" key={file.id}>
                    <a href={file.url} target="_blank" rel="noreferrer">{file.image ? <img src={file.url} alt={'Current file ' + (index + 1)}/> : 'Preview current PDF'}</a>
                    <label className="check"><input type="checkbox" checked={form.data.remove_media.includes(file.id)} onChange={e => form.setData('remove_media', e.target.checked ? [...form.data.remove_media, file.id] : form.data.remove_media.filter(id => id !== file.id))}/>Remove file</label>
                </div>)}
            </>}
            <label>Publication<select value={form.data.published ? '1' : '0'} onChange={event => form.setData('published', event.target.value === '1')}><option value="0">Draft</option><option value="1">Published</option></select></label>
            <Errors errors={form.errors}/>
            {form.progress && <progress value={form.progress.percentage} max={100} aria-label="Upload progress"/>}
            <button className="button primary" disabled={form.processing}>{form.processing ? 'Saving…' : 'Save ' + spec.item.toLowerCase()}</button>
        </form>
    </section>;
}

type Category = { id: number; name: string };

function CategoryManager({ categories }: { categories: Category[] }) {
    const [selected, setSelected] = useState('');
    const [busy, setBusy] = useState(false);
    const [failure, setFailure] = useState('');
    const form = useForm({ name: '' });
    const url = '/admin/skill-categories' + (selected ? '/' + selected : '');

    function reset() {
        setSelected('');
        setFailure('');
        form.reset();
    }

    return <details className="admin-panel category-manager">
        <summary>Manage skill categories</summary>
        <form onSubmit={event => {
            event.preventDefault();
            setFailure('');
            form.post(url, { preserveScroll: true, onSuccess: reset });
        }}>
            <label>Category<select value={selected} onChange={event => {
                const next = event.target.value;
                setSelected(next);
                setFailure('');
                form.clearErrors();
                form.setData('name', categories.find(category => String(category.id) === next)?.name || '');
            }}><option value="">Add a category</option>{categories.map(category => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label>
            <label>Category name<input required maxLength={100} value={form.data.name} onChange={e => form.setData('name', e.target.value)}/></label>
            <Errors errors={form.errors}/>
            {failure && <p className="error" role="alert">{failure}</p>}
            <div className="row-actions">
                <button className="button primary" disabled={busy || form.processing}>Save category</button>
                {selected && <button type="button" className="button secondary" disabled={busy || form.processing} onClick={() => {
                    if (!window.confirm('Delete this skill category?')) return;
                    setBusy(true);
                    router.delete(url, { preserveScroll: true, onSuccess: reset, onError: errors => setFailure(errors.category || 'Could not delete the category.'), onFinish: () => setBusy(false) });
                }}>Delete category</button>}
            </div>
        </form>
    </details>;
}

export function Records({ section, spec, items, categories, savedCategories, pagination }: { section: string; spec: Spec; items: Item[]; categories: string[]; savedCategories: Category[]; pagination: { links: PageLink[]; total: number } }) {
    const [editing, setEditing] = useState<Item | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Item | null>(null);
    const [deleteBusy, setDeleteBusy] = useState(false);
    const dialog = useRef<HTMLDialogElement>(null);
    const editor = useRef<HTMLDivElement>(null);

    useEffect(() => {
        setEditing(null);
        setDeleting(null);
    }, [section]);

    useEffect(() => {
        if (deleting) dialog.current?.showModal();
        else dialog.current?.close();
    }, [deleting]);

    useEffect(() => {
        if (editing) {
            editor.current?.scrollIntoView({ block: 'start' });
            editor.current?.querySelector<HTMLInputElement>('form input')?.focus({ preventScroll: true });
        }
    }, [editing]);

    return <AdminLayout title={spec.label} action={<button className="button primary" type="button" onClick={() => setEditing('new')}><Icon name="plus"/>Add {spec.item.toLowerCase()}</button>}>
        <div ref={editor} className="editor-anchor">
            {editing && <RecordEditor key={section + ':' + (editing === 'new' ? 'new' : editing.id)} section={section} spec={spec} item={editing === 'new' ? undefined : editing} categories={categories} close={() => setEditing(null)}/>}
        </div>
        {section === 'skills' && <CategoryManager categories={savedCategories}/>}
        <section className="admin-panel">
            {!items.length ? <p className="muted">{spec.empty}</p> : <ul className="record-list">{items.map(item => <li key={item.id}>
                <div><strong>{item.title}</strong> <span className={item.published ? 'status published' : 'status'}>{item.published ? 'Published' : 'Draft'}</span></div>
                <div className="row-actions">
                    {section === 'projects' && item.published && <a className="button secondary" href={'/projects/' + item.slug} target="_blank" rel="noreferrer">View</a>}
                    <button className="button secondary" type="button" onClick={() => setEditing(item)}>Edit</button>
                    <button className="button secondary" type="button" onClick={() => setDeleting(item)}>Delete</button>
                </div>
            </li>)}</ul>}
            <Pagination links={pagination.links}/>
        </section>
        <dialog ref={dialog} aria-labelledby="delete-heading" onClose={() => setDeleting(null)} onCancel={event => { if (deleteBusy) event.preventDefault(); }}>
            <h2 id="delete-heading">Delete {spec.item.toLowerCase()}?</h2>
            <p>“{deleting?.title}” and its attached files will be permanently removed.</p>
            <div className="actions">
                <button type="button" className="button secondary" autoFocus disabled={deleteBusy} onClick={() => setDeleting(null)}>Cancel</button>
                <button type="button" className="button danger" disabled={deleteBusy} onClick={() => {
                    if (!deleting) return;
                    setDeleteBusy(true);
                    router.delete('/admin/' + section + '/' + deleting.id, {
                        preserveScroll: true,
                        onSuccess: () => { if (editing === deleting) setEditing(null); setDeleting(null); },
                        onFinish: () => setDeleteBusy(false),
                    });
                }}>{deleteBusy ? 'Deleting…' : 'Delete'}</button>
            </div>
        </dialog>
    </AdminLayout>;
}

function crop(image: HTMLImageElement, zoom: number, horizontal: number, vertical: number) {
    const square = Math.min(image.naturalWidth, image.naturalHeight) / zoom;
    const x = (image.naturalWidth - square) * horizontal / 100;
    const y = (image.naturalHeight - square) * vertical / 100;
    const canvas = document.createElement('canvas');
    canvas.width = canvas.height = Math.min(640, Math.max(1, Math.round(square)));
    canvas.getContext('2d')?.drawImage(image, x, y, square, square, 0, 0, canvas.width, canvas.height);
    return canvas;
}

export function Settings({ kind, fields, values, photo, hiddenFields }: { kind: 'profile' | 'settings'; fields: Fields; values: Values; photo?: string | null; hiddenFields?: string[] }) {
    const [draft, setDraft] = useState(fieldValues(fields, values));
    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [image, setImage] = useState<HTMLImageElement | null>(null);
    const [zoom, setZoom] = useState(1);
    const [horizontal, setHorizontal] = useState(50);
    const [vertical, setVertical] = useState(50);
    const [photoError, setPhotoError] = useState('');
    const [encoding, setEncoding] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const form = useForm({ photo: null as File | null, remove_photo: false, hidden_fields: hiddenFields || [] });

    useEffect(() => {
        setImage(null);
        if (!file) return;
        let current = true;
        const url = URL.createObjectURL(file);
        const source = new Image();
        source.onload = () => { if (current) setImage(source); };
        source.onerror = () => { if (current) setPhotoError('This image could not be opened.'); };
        source.src = url;
        return () => { current = false; URL.revokeObjectURL(url); };
    }, [file]);

    useEffect(() => {
        setPreview(image ? crop(image, zoom, horizontal, vertical).toDataURL('image/webp') : null);
    }, [image, zoom, horizontal, vertical]);

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (file && !image) return;
        setEncoding(true);
        let upload: File | null = null;
        if (image) {
            const canvas = crop(image, zoom, horizontal, vertical);
            const blob = await new Promise<Blob | null>(resolve => canvas.toBlob(resolve, 'image/webp', 0.92));
            if (!blob) {
                setPhotoError('The crop could not be prepared.');
                setEncoding(false);
                return;
            }
            upload = new File([blob], blob.type === 'image/webp' ? 'profile.webp' : 'profile.png', { type: blob.type });
        }
        form.transform(data => ({ ...data, ...draft, photo: upload }));
        form.post('/admin/' + kind, {
            forceFormData: kind === 'profile',
            preserveScroll: true,
            onSuccess: () => {
                setFile(null);
                form.reset('photo', 'remove_photo');
                if (fileInput.current) fileInput.current.value = '';
            },
            onFinish: () => setEncoding(false),
        });
    }

    return <AdminLayout title={kind === 'profile' ? 'Profile' : 'Settings'}>
        <section className="admin-panel settings-panel"><form onSubmit={event => void submit(event)}>
            {kind === 'profile' && <fieldset className="photo-field">
                <legend>Profile photo</legend>
                <div className="photo-preview">{preview || (photo && !form.data.remove_photo) ? <img src={preview || photo || ''} alt="Profile photo preview"/> : <Icon name="user"/>}</div>
                <label>Choose photo<input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp" onChange={event => {
                    const choice = event.target.files?.[0] || null;
                    setPhotoError('');
                    setImage(null);
                    setPreview(null);
                    setZoom(1);
                    setHorizontal(50);
                    setVertical(50);
                    form.setData('remove_photo', false);
                    if (choice && (choice.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(choice.type))) {
                        setPhotoError('Choose a JPG, PNG, or WebP image under 5 MB.');
                        event.target.value = '';
                        setFile(null);
                        return;
                    }
                    setFile(choice);
                }}/><span className="form-hint">JPG, PNG, or WebP. Up to 5 MB.</span></label>
                {image && <div className="crop-controls">
                    <label>Zoom<input type="range" min="1" max="3" step="0.05" value={zoom} onChange={e => setZoom(Number(e.target.value))}/></label>
                    <label>Horizontal position<input type="range" min="0" max="100" value={horizontal} onChange={e => setHorizontal(Number(e.target.value))}/></label>
                    <label>Vertical position<input type="range" min="0" max="100" value={vertical} onChange={e => setVertical(Number(e.target.value))}/></label>
                    <button type="button" className="button secondary" onClick={() => { setFile(null); if (fileInput.current) fileInput.current.value = ''; }}>Discard selected photo</button>
                </div>}
                {photo && !file && <label className="check"><input type="checkbox" checked={form.data.remove_photo} onChange={e => form.setData('remove_photo', e.target.checked)}/>Remove current photo</label>}
                {photoError && <p className="error" role="alert">{photoError}</p>}
            </fieldset>}
            <FieldsEditor fields={fields} values={draft} onChange={setDraft}/>
            {kind === 'profile' && <fieldset className="photo-field">
                <legend>Public details</legend>
                {Object.entries(fields).map(([key, [label]]) => <label className="check" key={key}>
                    <input type="checkbox" checked={!form.data.hidden_fields.includes(key)} onChange={event => form.setData('hidden_fields', event.target.checked
                        ? form.data.hidden_fields.filter(field => field !== key) : [...form.data.hidden_fields, key])}/>{label}
                </label>)}
            </fieldset>}
            <Errors errors={form.errors}/>
            <button className="button primary" disabled={form.processing || encoding || Boolean(file && !image)}>{form.processing || encoding ? 'Saving…' : 'Save changes'}</button>
        </form></section>
    </AdminLayout>;
}
