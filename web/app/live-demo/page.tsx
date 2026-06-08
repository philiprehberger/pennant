import Link from 'next/link';

const adminBase = process.env.NEXT_PUBLIC_API_BASE || 'https://api.pennant.philiprehberger.com';
const productBase = process.env.NEXT_PUBLIC_PRODUCT_DEMO || 'https://pennant.philiprehberger.com/sample-app';

export default function LiveDemoPage() {
  return (
    <div className="space-y-6 pb-20">
      <header>
        <h1 className="text-3xl font-bold tracking-tight">Live demo — two tabs, one flag flip</h1>
        <p className="mt-3 max-w-3xl text-(--color-ink-dim)">
          Left frame is the Filament admin. Right frame is a sample product UI reading the flag through the React adapter. Flip the flag on the left; the right side updates over SSE in under 200ms on broadband. Open your DevTools network panel to watch the event arrive.
        </p>
        <p className="mt-3 max-w-3xl text-sm text-(--color-ink-dim)">
          Both frames are authenticated with the same per-visitor sandbox workspace key. Sandbox flag changes are isolated to your session and auto-purge after 24 hours.
        </p>
      </header>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <DemoFrame
          title="Admin"
          href={`${adminBase}/admin`}
          src={`${adminBase}/admin`}
          hint="Sign in with the per-visitor sandbox credentials shown on first visit, then toggle the example flag."
        />
        <DemoFrame
          title="Sample product"
          href={productBase}
          src={productBase}
          hint="A small React app calling useBool('new-checkout-flow'). It listens to the SSE stream and re-renders only the bound component."
        />
      </div>

      <aside className="rounded-lg border border-(--color-paper-dim) bg-(--color-paper) p-5 text-sm text-(--color-ink-dim)">
        <strong className="text-(--color-ink)">Heads-up:</strong> the live demo requires the API to be deployed. While the API is being provisioned, both frames will load placeholder content. The <Link href="/reference">API reference</Link> shows the wire format; the <Link href="/bucketing">bucketing visualizer</Link> is self-contained.
      </aside>
    </div>
  );
}

function DemoFrame({ title, href, src, hint }: { title: string; href: string; src: string; hint: string }) {
  return (
    <div className="overflow-hidden rounded-lg border border-(--color-paper-dim) bg-white">
      <div className="flex items-center justify-between border-b border-(--color-paper-dim) px-4 py-2">
        <span className="text-sm font-semibold">{title}</span>
        <a href={href} target="_blank" rel="noreferrer" className="text-xs">
          Open in new tab ↗
        </a>
      </div>
      <iframe src={src} title={title} className="h-[600px] w-full border-none" />
      <div className="border-t border-(--color-paper-dim) px-4 py-2 text-xs text-(--color-ink-dim)">{hint}</div>
    </div>
  );
}
