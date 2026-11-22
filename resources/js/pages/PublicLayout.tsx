import { useEffect, useState, type CSSProperties, type ReactNode } from 'react';
import { Icon } from '../Icons';
import type { PublicData } from '../types';

interface InstallPrompt extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: string }>;
}

export function isOfflineSnapshot() {
    return document.querySelector('meta[name="portfolio-offline"]') !== null;
}

export function PublicLayout({ site, profile, children }: PublicData & { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const [install, setInstall] = useState<InstallPrompt | null>(null);
    const [installHelp, setInstallHelp] = useState(false);
    const [offline, setOffline] = useState(() => !navigator.onLine || isOfflineSnapshot());
    const [installed, setInstalled] = useState(() => matchMedia('(display-mode: standalone)').matches ||
        Boolean((navigator as Navigator & { standalone?: boolean }).standalone));
    const name = site.name || profile.name || 'Personal Portfolio';

    useEffect(() => {
        function handleInstall(event: Event) {
            event.preventDefault();
            setInstall(event as InstallPrompt);
        }
        function handleInstalled() { setInstalled(true); setInstall(null); }
        function updateNetwork() { setOffline(!navigator.onLine); }
        window.addEventListener('beforeinstallprompt', handleInstall);
        window.addEventListener('appinstalled', handleInstalled);
        window.addEventListener('online', updateNetwork);
        window.addEventListener('offline', updateNetwork);
        return () => {
            window.removeEventListener('beforeinstallprompt', handleInstall);
            window.removeEventListener('appinstalled', handleInstalled);
            window.removeEventListener('online', updateNetwork);
            window.removeEventListener('offline', updateNetwork);
        };
    }, []);

    async function promptInstall() {
        if (!install) {
            setInstallHelp(!installHelp);
            return;
        }
        await install.prompt();
        const result = await install.userChoice;
        if (result.outcome === 'accepted') setInstalled(true);
        setInstall(null);
    }

    return <div className="public-site" style={site.accent ? { '--accent': site.accent } as CSSProperties : undefined}>
        <a className="skip" href="#main">Skip to content</a>
        <header className="site-header">
            <div className="wrap nav-layout">
                <a className="brand" href="/"><i><Icon name="mark"/></i><span>{name}</span></a>
                <button className="menu-toggle button secondary" aria-label={open ? 'Close navigation' : 'Open navigation'} aria-expanded={open} aria-controls="site-nav" onClick={() => setOpen(!open)}><Icon name={open ? 'close' : 'menu'}/></button>
                <nav id="site-nav" className={open ? 'site-nav shown' : 'site-nav'} aria-label="Main navigation" onKeyDown={event => { if (event.key === 'Escape') setOpen(false); }}>
                    {[['Projects', 'projects'], ['About', 'about'], ['Credentials', 'credentials']].map(([label, id]) =>
                        <a key={id} href={'/#' + id} onClick={() => setOpen(false)}>{label}</a>)}
                    <a className="button secondary nav-contact" href="/#contact" onClick={() => setOpen(false)}>Contact Me</a>
                </nav>
            </div>
        </header>
        {offline && <div className="offline-banner" role="status">You’re offline. You may be viewing a saved version of this portfolio.</div>}
        <main id="main">{children}</main>
        <footer className="site-footer">
            <div className="wrap footer-layout">
                <a className="brand" href="/"><i><Icon name="mark"/></i><span>{name}</span></a>
                <div className="footer-actions">
                    {!installed && <button className="text-button" aria-expanded={installHelp} onClick={() => void promptInstall()}>Install app</button>}
                    <a href="/admin/login">Owner sign in</a>
                </div>
                {installHelp && <p className="install-help" role="status">On iPhone, open Safari’s Share menu and select Add to Home Screen. On other devices, look for Install app or Add to Home screen in the browser menu.</p>}
            </div>
        </footer>
        <a className="button primary floating-contact" href="/#contact"><Icon name="mail"/> Contact Me</a>
    </div>;
}
