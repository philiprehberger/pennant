import Link from 'next/link';

const tsExample = `import { Pennant } from '@philiprehberger/pennant';

const flags = new Pennant({
  apiBase: 'https://api.pennant.philiprehberger.com',
  apiKey: process.env.NEXT_PUBLIC_PENNANT_KEY!,
  environment: 'prod',
  context: { userId: 'alice', plan: 'enterprise' },
});

if (flags.bool('new-checkout-flow', false)) {
  // ...
}`;

const reactExample = `import { PennantProvider, useBool } from '@philiprehberger/pennant-react';

function Checkout() {
  // Re-renders only when this specific flag changes.
  const enabled = useBool('new-checkout-flow', false);
  return enabled ? <NewCheckout /> : <LegacyCheckout />;
}

export default function App() {
  return (
    <PennantProvider options={{ apiBase, apiKey, environment, context }}>
      <Checkout />
    </PennantProvider>
  );
}`;

const phpExample = `use Pennant\\Pennant;

$flags = new Pennant([
    'api_base' => 'https://api.pennant.philiprehberger.com',
    'api_key' => getenv('PENNANT_KEY'),
    'environment' => 'prod',
    'context' => ['userId' => 'alice', 'plan' => 'enterprise'],
]);

if ($flags->bool('new-checkout-flow', false)) {
    // ...
}`;

const pythonExample = `from pennant import Pennant

flags = Pennant(
    api_base="https://api.pennant.philiprehberger.com",
    api_key=os.environ["PENNANT_KEY"],
    environment="prod",
    context={"userId": "alice", "plan": "enterprise"},
)

if flags.bool("new-checkout-flow", False):
    ...`;

const samples: [string, string][] = [
  ['TypeScript', tsExample],
  ['React', reactExample],
  ['PHP', phpExample],
  ['Python', pythonExample],
];

export default function Home() {
  return (
    <>
      <section className="pt-10 pb-20">
        <p className="text-sm font-semibold uppercase tracking-widest text-(--color-accent)">
          Feature flags · real-time SDK · production-shaped
        </p>
        <h1 className="mt-3 text-5xl font-bold tracking-tight">
          A feature flag service that ships the whole product surface a product team expects — not just an API behind a README.
        </h1>
        <p className="mt-6 max-w-3xl text-lg text-(--color-ink-dim)">
          Pennant is a portfolio demonstration. A Laravel API with a targeting rule engine, a Filament admin with audit trails, a Node SSE broadcaster, and four SDKs that share a cross-implementation drift corpus — so the rule engine on your server and the rule engine in the browser can never disagree about who's in the rollout.
        </p>
        <div className="mt-8 flex flex-wrap items-center gap-4">
          <Link
            href="/live-demo"
            className="rounded-md bg-(--color-ink) px-5 py-3 text-sm font-semibold text-white no-underline hover:bg-(--color-accent)"
          >
            Open the live demo
          </Link>
          <Link
            href="/reference"
            className="rounded-md border border-(--color-ink) px-5 py-3 text-sm font-semibold text-(--color-ink) no-underline hover:bg-(--color-paper-dim)"
          >
            Browse the API reference
          </Link>
          <a
            href="https://github.com/philiprehberger/pennant"
            className="rounded-md px-5 py-3 text-sm font-semibold text-(--color-ink-dim) no-underline hover:bg-(--color-paper-dim)"
          >
            Source on GitHub
          </a>
        </div>
      </section>

      <section className="border-y border-(--color-paper-dim) py-16">
        <h2 className="text-2xl font-bold tracking-tight">Four SDKs, one contract</h2>
        <p className="mt-3 max-w-3xl text-(--color-ink-dim)">
          TypeScript core, React adapter, PHP / Laravel, Python / Django / FastAPI. Every implementation runs the same drift corpus in its native test runner. Sub-200ms updates via SSE; deterministic SHA-256 bucketing locally so percentage rollouts don't flicker across page refreshes.
        </p>
        <div className="mt-10 grid gap-6 lg:grid-cols-2">
          {samples.map(([title, code]) => (
            <div key={title} className="overflow-hidden rounded-lg border border-(--color-paper-dim)">
              <div className="border-b border-(--color-paper-dim) bg-(--color-paper-dim) px-4 py-2 text-xs font-semibold uppercase tracking-wider text-(--color-ink-dim)">
                {title}
              </div>
              <pre className="m-0 rounded-none bg-(--color-ink) p-5 text-[12.5px] leading-relaxed text-white">
                <code>{code}</code>
              </pre>
            </div>
          ))}
        </div>
      </section>

      <section className="py-16">
        <h2 className="text-2xl font-bold tracking-tight">What's actually in the box</h2>
        <div className="mt-8 grid gap-8 md:grid-cols-2">
          <Pillar title="Rule engine that doesn't drift">
            <p>
              Ten operators, <code>all</code>/<code>any</code>/<code>none</code> combinators, segment references with static cycle detection, dotted attribute paths. The same evaluator runs on the Laravel server and inside every SDK. A 34-case drift corpus + 5 bucketing cases at <code>tests/corpus/rules.json</code> round-trips through each implementation in CI. <strong>If the server says yes and the browser says no, that's a bug, and the corpus catches it.</strong>
            </p>
          </Pillar>
          <Pillar title="Real-time without the ops sprawl">
            <p>
              A small Node SSE broadcaster subscribes to Redis pub/sub; the Laravel API publishes a tiny envelope on every flag configuration write. SDKs hold a long-lived <code>EventSource</code> and apply diffs to their in-memory snapshot. <code>Last-Event-ID</code> replay covers brief disconnects; polling fallback covers everything else.
            </p>
          </Pillar>
          <Pillar title="Offline-resilient client SDKs">
            <p>
              The TypeScript SDK persists last-known-good snapshots to <code>localStorage</code> (browser) or memory (Node) and emits <code>ready</code> immediately on cold start even with no network. The cache survives malformed JSON and storage quota errors without ever crashing your app.
            </p>
          </Pillar>
          <Pillar title="Two key kinds, one safe browser story">
            <p>
              <code>pn_srv_</code> keys see the full rule logic. <code>pn_clt_</code> keys receive <em>pre-evaluated</em> values for the context attached to the request — the rules never leave the server. The same SDK handles both transparently.
            </p>
          </Pillar>
          <Pillar title="Multi-environment + audit log + kill switch">
            <p>
              Per-workspace environments (dev / staging / prod / custom), promote-between-environments with a diff, audit log on every mutation captured with the actor and reason, and a one-click kill-switch for production with a mandatory reason field.
            </p>
          </Pillar>
          <Pillar title="Built like infrastructure, framed honestly">
            <p>
              This is a portfolio demonstration, not a production service — see the <Link href="/about">about page</Link> for the framing. Every feature here is the kind of thing a team has to build for itself if it doesn't outsource the work. The point of the demo is to make that decision easier.
            </p>
          </Pillar>
        </div>
      </section>
    </>
  );
}

function Pillar({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div>
      <h3 className="text-lg font-semibold">{title}</h3>
      <div className="mt-2 text-(--color-ink-dim) leading-relaxed">{children}</div>
    </div>
  );
}
