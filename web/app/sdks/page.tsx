const sdks = [
  {
    title: 'TypeScript / Node',
    install: 'npm install @philiprehberger/pennant',
    notes: 'Browser + Node 22+. SSE primary, polling fallback, localStorage persistence.',
    href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/typescript',
  },
  {
    title: 'React',
    install: 'npm install @philiprehberger/pennant @philiprehberger/pennant-react',
    notes: '`useBool`/`useString`/`useNumber`/`useJson` hooks with selective re-render via `useSyncExternalStore`.',
    href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/react',
  },
  {
    title: 'PHP / Laravel',
    install: 'composer require philiprehberger/pennant',
    notes: 'Laravel service provider auto-registers; `@pennant` Blade directive + `Pennant` facade. PHP 8.3+.',
    href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/php',
  },
  {
    title: 'Python / Django / FastAPI',
    install: 'pip install pennant',
    notes: 'Django middleware + `flag_required` decorator; FastAPI dependency. Python 3.10+.',
    href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/python',
  },
];

const v2 = ['Vue 3 composable', 'Svelte store', 'Go module', 'Ruby gem', 'Java / Kotlin', 'iOS / Swift', '.NET / C#'];

export default function SdksPage() {
  return (
    <div className="space-y-12 pb-20">
      <header>
        <h1 className="text-3xl font-bold tracking-tight">SDKs</h1>
        <p className="mt-3 max-w-3xl text-(--color-ink-dim)">
          Each implementation ships its own evaluator + bucketing function. The cross-implementation drift corpus at <code>tests/corpus/rules.json</code> runs through all of them in CI — if any implementation disagrees with the server, CI fails on every implementation simultaneously.
        </p>
      </header>

      <section className="grid gap-6 md:grid-cols-2">
        {sdks.map((s) => (
          <div key={s.title} className="rounded-lg border border-(--color-paper-dim) bg-white p-6">
            <h2 className="text-lg font-semibold">{s.title}</h2>
            <pre className="mt-3 rounded bg-(--color-ink) p-3 text-xs text-white"><code>{s.install}</code></pre>
            <p className="mt-3 text-sm text-(--color-ink-dim)">{s.notes}</p>
            <a href={s.href} className="mt-3 inline-block text-sm">View source ↗</a>
          </div>
        ))}
      </section>

      <section>
        <h2 className="text-xl font-semibold">v2 SDKs — open ports</h2>
        <p className="mt-2 text-sm text-(--color-ink-dim)">
          These are next, gated on buyer signal. Each follows the same shape — port the bucketing function first, then the rule evaluator, then the client. The corpus is the contract; the rest is mechanical.
        </p>
        <ul className="mt-4 flex flex-wrap gap-2">
          {v2.map((lang) => (
            <li key={lang} className="rounded-full border border-(--color-paper-dim) px-3 py-1 text-sm text-(--color-ink-dim)">
              {lang}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
