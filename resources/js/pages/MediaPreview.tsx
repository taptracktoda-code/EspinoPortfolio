import { Icon } from '../Icons';
import type { Item } from '../types';

export function MediaPreview({ item }: { item: Item }) {
    return <div className="media-list">{item.media.map((file, index) => file.image
        ? <a key={file.id} href={file.url} target="_blank" rel="noreferrer"><img src={file.url} alt={item.title + ' · ' + (index + 1)} loading="lazy"/></a>
        : <a key={file.id} className="button secondary" href={file.url} target="_blank" rel="noreferrer"><Icon name="file"/> Preview PDF</a>)}</div>;
}
