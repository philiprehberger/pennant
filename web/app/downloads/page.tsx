import Link from 'next/link';

const downloads = [
  { name: 'OpenAPI spec (YAML)', href: '/openapi.yaml', size: 'human-readable' },
  { name: 'OpenAPI spec (JSON)', href: '/openapi.json', size: 'machine-readable' },
  { name: 'TypeScript SDK source', href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/typescript', size: 'GitHub' },
  { name: 'React adapter source', href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/react', size: 'GitHub' },
  { name: 'PHP / Laravel SDK source', href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/php', size: 'GitHub' },
  { name: 'Python / Django / FastAPI SDK source', href: 'https://github.com/philiprehberger/pennant/tree/main/sdks/python', size: 'GitHub' },
  { name: 'Rule-engine drift corpus', href: 'https://github.com/philiprehberger/pennant/blob/main/tests/corpus/rules.json', size: 'GitHub' },
];

export default function DownloadsPage() {
  return (
    <div className="pb-20">
      <h1 className="text-3xl font-bold tracking-tight">Downloads</h1>
      <p className="mt-3 max-w-3xl text-(--color-ink-dim)">
        Spec + per-SDK sources. SDKs publish to npm / Packagist / PyPI on tag
        push — see{' '}
        <Link href="/sdks">/sdks</Link> for install commands.
      </p>

      <ul className="mt-10 divide-y divide-(--color-paper-dim) overflow-hidden rounded-lg border border-(--color-paper-dim) bg-white">
        {downloads.map((d) => (
          <li key={d.name} className="flex items-center justify-between px-5 py-4">
            <div>
              <a href={d.href} className="font-medium text-(--color-ink)">
                {d.name}
              </a>
              <span className="ml-3 text-xs text-(--color-ink-dim)">{d.size}</span>
            </div>
            <a
              href={d.href}
              className="rounded border border-(--color-paper-dim) px-3 py-1.5 text-sm no-underline text-(--color-ink) hover:bg-(--color-paper)"
            >
              Open
            </a>
          </li>
        ))}
      </ul>
    </div>
  );
}
