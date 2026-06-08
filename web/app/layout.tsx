import './globals.css';
import Link from 'next/link';
import type { ReactNode } from 'react';

export const metadata = {
  title: 'Pennant — feature flag API + real-time SDK',
  description:
    'Production-shaped feature flag and remote-config service with a targeting rule engine, real-time SSE push, multi-environment workflow, audit log, and SDKs that survive offline starts.',
  metadataBase: new URL('https://pennant.philiprehberger.com'),
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en">
      <body>
        <header className="border-b border-(--color-paper-dim) bg-(--color-paper)">
          <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <Link href="/" className="text-lg font-bold tracking-tight text-(--color-ink) no-underline">
              Pennant
              <span className="ml-2 rounded-full bg-(--color-accent-soft) px-2 py-0.5 text-xs font-medium text-(--color-accent) align-middle">
                portfolio demo
              </span>
            </Link>
            <nav className="flex items-center gap-6 text-sm text-(--color-ink-dim)">
              <Link href="/live-demo">Live demo</Link>
              <Link href="/reference">API reference</Link>
              <Link href="/bucketing">Bucketing</Link>
              <Link href="/sdks">SDKs</Link>
              <Link href="/downloads">Downloads</Link>
              <Link href="/about">About</Link>
            </nav>
          </div>
        </header>
        <main className="mx-auto max-w-6xl px-6 py-10">{children}</main>
        <footer className="border-t border-(--color-paper-dim) mt-20">
          <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-8 text-sm text-(--color-ink-dim)">
            <div>
              Pennant is a portfolio demonstration by{' '}
              <a href="https://philiprehberger.com">Philip Rehberger</a>.
            </div>
            <div className="flex gap-4">
              <a href="https://github.com/philiprehberger/pennant">GitHub</a>
              <Link href="/about">About this demo</Link>
            </div>
          </div>
        </footer>
      </body>
    </html>
  );
}
